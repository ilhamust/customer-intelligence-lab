<?php

namespace App\Service\Bot\Implements;

use App\Events\Bot\ConversationUpdated;
use App\Events\Bot\MessageReceived;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Jobs\Bot\AutoRestoreAiHandlerJob;
use App\Jobs\Bot\GenerateAiReplyJob;
use App\Models\Bot\Conversation;
use App\Models\Bot\Customer;
use App\Models\Bot\Message;
use App\Models\Bot\WhatsappChannel;
use App\Service\Bot\Contracts\ConversationService;
use App\Service\Bot\Contracts\CustomerRegistrationService;
use App\Service\Bot\Contracts\WifiTokenService;
use App\Service\Whatsapp\Contracts\WhatsappGatewayService;
use App\Traits\ServiceHelper;
use Carbon\Carbon;
use Exception;

class ConversationServiceImpl implements ConversationService
{
    use ServiceHelper;

    public function __construct(
        protected WhatsappGatewayService $whatsappGateway,
        protected CustomerRegistrationService $customerRegistrationService,
        protected WifiTokenService $wifiTokenService
    ) {}

    /**
     * Resolve target WhatsApp device ID.
     */
    protected function resolveDeviceId(?string $deviceId = null): ?string
    {
        if ($deviceId) {
            $cleanPhone = preg_replace('/[^0-9]/', '', explode('@', $deviceId)[0]);

            $channel = WhatsappChannel::where('device_id', $deviceId)
                ->orWhere('jid', $deviceId)
                ->orWhere('phone_number', $cleanPhone)
                ->first();

            if ($channel) {
                return $channel->device_id;
            }

            return $deviceId;
        }

        $channel = WhatsappChannel::where('status', 'connected')->first()
            ?? WhatsappChannel::where('is_active', true)->first()
            ?? WhatsappChannel::first();

        return $channel?->device_id;
    }

    /**
     * List all conversations with filters and formatted data.
     */
    public function list(array $filters = []): array
    {
        $m = __METHOD__;
        $this->logInfo($m, 'Listing conversations', ['filters' => $filters]);

        $query = Conversation::with([
            'customer',
            'messages' => function ($q) {
                $q->orderBy('sent_at', 'asc');
            },
        ]);

        if (! empty($filters['device_id'])) {
            $query->where('device_id', $filters['device_id']);
        }

        $status = $filters['status'] ?? 'all';
        if ($status === 'unread') {
            $query->where('unread_count', '>', 0);
        } elseif ($status === 'escalated') {
            $query->where(function ($q) {
                $q->where('status', 'escalated')
                    ->orWhere('handler', 'human');
            });
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('chat_jid', 'like', "%{$search}%")
                    ->orWhere('last_message', 'ilike', "%{$search}%");
            });
        }

        $conversations = $query->orderByRaw('last_message_time DESC NULLS LAST')
            ->orderBy('updated_at', 'desc')
            ->get();

        $data = $conversations->map(fn ($c) => $this->formatConversation($c))->all();

        return [
            'data' => $data,
            'summary' => [
                'all' => Conversation::count(),
                'unread' => Conversation::where('unread_count', '>', 0)->count(),
                'escalated' => Conversation::where('status', 'escalated')->orWhere('handler', 'human')->count(),
            ],
        ];
    }

    /**
     * Get a single conversation by ID.
     */
    public function show(string $conversationId): Conversation
    {
        $conversation = Conversation::with(['customer'])->find($conversationId);

        if (! $conversation) {
            throw new NotFoundException("Conversation [{$conversationId}] not found.");
        }

        // Reset unread count when viewed
        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }

        return $conversation;
    }

    /**
     * Get message history for a conversation, retrieving the most recent messages.
     */
    public function getMessages(string $conversationId, int $limit = 100): array
    {
        $conversation = $this->show($conversationId);

        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('sent_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        return $messages->map(fn ($m) => $this->formatMessage($m))->all();
    }

    /**
     * Send an outgoing message in a conversation via WhatsApp Gateway and persist to DB.
     */
    public function sendMessage(string $conversationId, string $text, ?string $senderType = 'human'): Message
    {
        $m = __METHOD__;
        $conversation = Conversation::find($conversationId);

        if (! $conversation) {
            throw new NotFoundException("Conversation [{$conversationId}] not found.");
        }

        $trimmedText = trim($text);
        if (empty($trimmedText)) {
            throw new BadRequestException('Message content cannot be empty.');
        }

        // Send via WhatsApp Gateway
        $sent = $this->whatsappGateway->sendText(
            $conversation->phone,
            $trimmedText,
            $conversation->device_id
        );

        // end typing status
        $this->whatsappGateway->sendChatPresence(
            $conversation->phone,
            'stop',
            $conversation->device_id
        );

        if (! $sent) {
            $this->logWarning($m, 'Failed sending WhatsApp message via gateway, saving with failed status', [
                'conversation_id' => $conversationId,
                'phone' => $conversation->phone,
            ]);
        }

        // Create Message Record
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'whatsapp_message_id' => null,
            'sender_type' => $senderType ?: 'human',
            'is_from_me' => true,
            'text' => $trimmedText,
            'status' => $sent ? 'delivered' : 'failed',
            'sent_at' => now(),
        ]);

        // Update Conversation (reset unread_count to 0 when replying)
        $conversation->update([
            'last_message' => $trimmedText,
            'last_message_time' => now(),
            'unread_count' => 0,
        ]);

        $this->logInfo($m, 'Message sent and stored', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'status' => $message->status,
        ]);

        // Jadwalkan auto-restore ke AI setelah 10 menit inaktivitas pesan manual
        if (($senderType ?: 'human') === 'human') {
            AutoRestoreAiHandlerJob::dispatch($conversation->id)->delay(now()->addMinutes(10));
        }

        // Broadcast to WebSocket clients
        broadcast(new MessageReceived($message, $conversation));

        return $message;
    }

    /**
     * Synchronize conversations and recent messages from GOWA into local database.
     */
    public function syncFromGowa(?string $deviceId = null): array
    {
        $m = __METHOD__;
        $id = $this->resolveDeviceId($deviceId);

        if (! $id) {
            $this->logWarning($m, 'No WhatsApp device configured or connected for sync');

            return [
                'synced_conversations' => 0,
                'synced_messages' => 0,
                'message' => 'No active WhatsApp device found.',
            ];
        }

        $this->logInfo($m, 'Starting GOWA synchronization', ['device_id' => $id]);

        $chats = $this->whatsappGateway->getChats($id, 100);
        $syncedConvCount = 0;
        $syncedMsgCount = 0;

        foreach ($chats as $chat) {
            $chatJid = $chat['jid'] ?? null;
            if (! $chatJid || str_contains($chatJid, 'broadcast') || $chatJid === '0@s.whatsapp.net') {
                continue;
            }

            $phone = preg_replace('/[^0-9]/', '', explode('@', $chatJid)[0]);
            $name = ! empty($chat['name']) ? $chat['name'] : $phone;

            $lastTime = null;
            if (! empty($chat['last_message_time']) && ! str_starts_with($chat['last_message_time'], '0001')) {
                try {
                    $lastTime = Carbon::parse($chat['last_message_time']);
                } catch (Exception) {
                    $lastTime = null;
                }
            }

            $conversation = Conversation::updateOrCreate(
                [
                    'device_id' => $id,
                    'chat_jid' => $chatJid,
                ],
                [
                    'name' => $name,
                    'phone' => $phone,
                    'last_message_time' => $lastTime,
                    'status' => 'all',
                    'handler' => 'ai',
                ]
            );

            $syncedConvCount++;

            // Fetch recent messages for this chat
            $gowaMessages = $this->whatsappGateway->getChatMessages($chatJid, $id, 30);
            if (! empty($gowaMessages) && is_array($gowaMessages)) {
                $latestMessageText = null;
                $latestMessageTime = null;

                foreach ($gowaMessages as $gMsg) {
                    $msgId = $gMsg['id'] ?? null;
                    $content = $gMsg['content'] ?? '';
                    $isFromMe = (bool) ($gMsg['is_from_me'] ?? false);

                    $sentAt = now();
                    if (! empty($gMsg['timestamp'])) {
                        try {
                            $sentAt = Carbon::parse($gMsg['timestamp']);
                        } catch (Exception) {
                            $sentAt = now();
                        }
                    }

                    // Avoid duplicate messages
                    $existingMsg = null;
                    if ($msgId) {
                        $existingMsg = Message::where('conversation_id', $conversation->id)
                            ->where('whatsapp_message_id', $msgId)
                            ->first();
                    }

                    // If this is an outbound message and not found by WhatsApp ID,
                    // match against local messages that were sent with whatsapp_message_id = null
                    if (! $existingMsg && $isFromMe) {
                        $pendingOutbound = Message::where('conversation_id', $conversation->id)
                            ->where('is_from_me', true)
                            ->whereNull('whatsapp_message_id')
                            ->where('text', $content)
                            ->whereBetween('sent_at', [
                                $sentAt->copy()->subMinutes(5),
                                $sentAt->copy()->addMinutes(5),
                            ])
                            ->orderBy('sent_at', 'desc')
                            ->first();

                        if ($pendingOutbound) {
                            if ($msgId) {
                                $pendingOutbound->update([
                                    'whatsapp_message_id' => $msgId,
                                    'sent_at' => $sentAt,
                                ]);
                            }
                            $existingMsg = $pendingOutbound;
                        }
                    }

                    if (! $existingMsg) {
                        Message::create([
                            'conversation_id' => $conversation->id,
                            'whatsapp_message_id' => $msgId,
                            'sender_type' => $isFromMe ? 'human' : 'customer',
                            'is_from_me' => $isFromMe,
                            'text' => $content,
                            'status' => 'delivered',
                            'sent_at' => $sentAt,
                            'meta' => [
                                'media_type' => $gMsg['media_type'] ?? '',
                                'filename' => $gMsg['filename'] ?? '',
                                'sender_jid' => $gMsg['sender_jid'] ?? '',
                            ],
                        ]);
                        $syncedMsgCount++;
                    }

                    // Track latest message
                    if (! $latestMessageTime || $sentAt->greaterThan($latestMessageTime)) {
                        $latestMessageTime = $sentAt;
                        $latestMessageText = $content;
                    }
                }

                if ($latestMessageText !== null) {
                    $conversation->update([
                        'last_message' => $latestMessageText,
                        'last_message_time' => $latestMessageTime,
                    ]);
                }
            }
        }

        $this->logInfo($m, 'GOWA sync completed', [
            'device_id' => $id,
            'conversations' => $syncedConvCount,
            'messages' => $syncedMsgCount,
        ]);

        return [
            'synced_conversations' => $syncedConvCount,
            'synced_messages' => $syncedMsgCount,
            'device_id' => $id,
            'message' => 'Synchronization successfully completed.',
        ];
    }

    /**
     * Process an incoming message webhook payload from GOWA and persist to DB.
     */
    public function handleIncomingMessageWebhook(array $payload, ?string $deviceId = null): ?Message
    {
        $m = __METHOD__;
        $id = $this->resolveDeviceId($deviceId ?: ($payload['device_id'] ?? null));

        $data = $payload['payload'] ?? $payload;
        $chatJid = $data['from'] ?? $data['chat_jid'] ?? $data['sender'] ?? null;

        if (! $chatJid || str_contains($chatJid, 'broadcast')) {
            $this->logInfo($m, 'Ignoring incoming message webhook without valid chat JID', ['data' => $data]);

            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', explode('@', $chatJid)[0]);
        $pushName = $data['push_name'] ?? $data['sender_display_name'] ?? $data['name'] ?? null;
        $isFromMe = (bool) ($data['is_from_me'] ?? false);
        $text = $data['body'] ?? $data['content'] ?? $data['text'] ?? '';
        $messageId = $data['id'] ?? $data['whatsapp_message_id'] ?? null;

        $sentAt = now();
        if (! empty($data['timestamp'])) {
            try {
                $sentAt = Carbon::parse($data['timestamp']);
            } catch (Exception) {
                $sentAt = now();
            }
        }

        // Find or create conversation (match by device_id & chat_jid, with fallback by chat_jid)
        $conversation = Conversation::where('chat_jid', $chatJid)
            ->where(function ($q) use ($id) {
                if ($id) {
                    $q->where('device_id', $id)
                        ->orWhere('device_id', explode('@', $id)[0]);
                }
            })
            ->first();

        if (! $conversation) {
            $conversation = Conversation::where('chat_jid', $chatJid)->first();
            if ($conversation && $id && $conversation->device_id !== $id) {
                $conversation->update(['device_id' => $id]);
            }
        }

        if (! $conversation) {
            $conversation = Conversation::create([
                'device_id' => $id,
                'chat_jid' => $chatJid,
                'name' => $pushName ?: $phone,
                'phone' => $phone,
                'status' => 'all',
                'handler' => 'ai',
                'unread_count' => 0,
            ]);
        }

        if ($pushName && empty($conversation->name)) {
            $conversation->name = $pushName;
        }

        // Check duplicate message
        if ($messageId) {
            $existing = Message::where('conversation_id', $conversation->id)
                ->where('whatsapp_message_id', $messageId)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        // If outbound message, match with local pending message without whatsapp_message_id
        if ($isFromMe) {
            $pendingMsg = Message::where('conversation_id', $conversation->id)
                ->where('is_from_me', true)
                ->whereNull('whatsapp_message_id')
                ->where('text', $text)
                ->whereBetween('sent_at', [
                    $sentAt->copy()->subMinutes(5),
                    $sentAt->copy()->addMinutes(5),
                ])
                ->orderBy('sent_at', 'desc')
                ->first();

            if ($pendingMsg) {
                if ($messageId) {
                    $pendingMsg->update([
                        'whatsapp_message_id' => $messageId,
                        'sent_at' => $sentAt,
                    ]);
                }

                return $pendingMsg;
            }
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'whatsapp_message_id' => $messageId,
            'sender_type' => $isFromMe ? 'human' : 'customer',
            'is_from_me' => $isFromMe,
            'text' => $text,
            'status' => 'delivered',
            'sent_at' => $sentAt,
            'meta' => $data,
        ]);

        // Lazy auto-restore: Jika handler saat ini adalah human, cek apakah percakapan sudah inaktif >= 10 menit
        // Pengecualian: Jangan auto-restore jika percakapan memiliki tiket aktif (queued atau in_progress)
        if (! $isFromMe && $conversation->handler === 'human') {
            $hasActiveTicket = $conversation->tickets()
                ->whereIn('status', ['queued', 'in_progress'])
                ->exists();

            if (! $hasActiveTicket) {
                $cutoff = now()->subMinutes(10);
                $lastActivity = $conversation->last_message_time;

                if (! $lastActivity || Carbon::parse($lastActivity)->lessThanOrEqualTo($cutoff)) {
                    $conversation->handler = 'ai';
                    $conversation->status = 'all';

                    $this->logInfo($m, 'Inactivity threshold (10m) reached on inbound message, auto-restoring handler to "ai"', [
                        'conversation_id' => $conversation->id,
                        'last_activity' => $lastActivity,
                    ]);

                    // Broadcast perubahan handler ke WebSocket
                    broadcast(new ConversationUpdated($conversation));
                }
            }
        }

        $conversation->last_message = $text;
        $conversation->last_message_time = $sentAt;
        if (! $isFromMe) {
            $conversation->unread_count += 1;
        }
        $conversation->save();

        $this->logInfo($m, 'Incoming message processed and saved', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
        ]);

        // Broadcast incoming message to WebSocket clients
        broadcast(new MessageReceived($message, $conversation));

        // Intersepsi Tiket Aktif: Jika percakapan memiliki tiket berstatus queued atau in_progress,
        // pesan sudah disimpan dan disiarkan ke dashboard CS (di atas), lalu bypass GenerateAiReplyJob
        // agar bot AI tetap diam (silent mode) selama pelanggan menunggu dalam antrean atau berbicara dengan CS.
        $hasActiveTicket = $conversation->tickets()
            ->whereIn('status', ['queued', 'in_progress'])
            ->exists();

        if ($hasActiveTicket) {
            $this->logInfo($m, 'Conversation has an active ticket (queued/in_progress). Bypassing AI auto-reply to keep bot silent', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
            ]);

            return $message;
        }

        // Automatically handle registration flow for new customers or dispatch AI reply for registered customers
        if (! $isFromMe && $conversation->handler === 'ai') {
            $phone = $conversation->phone ?: preg_replace('/[^0-9]/', '', explode('@', $conversation->chat_jid)[0]);

            // Cek apakah customer sudah terdaftar di database
            $customer = $conversation->customer_id
                ? Customer::find($conversation->customer_id)
                : Customer::where('phone', $phone)->first();

            // Sinkronkan percakapan jika customer sudah ada di database tapi belum tertaut
            if ($customer && (! $conversation->customer_id || $conversation->registration_step !== 'completed')) {
                $conversation->update([
                    'customer_id' => $customer->id,
                    'registration_step' => 'completed',
                    'name' => $customer->name,
                ]);
            }

            // JIKA BELUM TERDAFTAR: Jalankan alur registrasi pelanggan baru
            if (! $customer) {
                $step = $conversation->registration_step ?? 'unregistered';

                if ($step === 'unregistered') {
                    // Chat pertama kali: Kirim form registrasi singkat
                    $formText = $this->customerRegistrationService->getRegistrationFormText();
                    $this->sendMessage($conversation->id, $formText, 'bot');

                    $conversation->update(['registration_step' => 'awaiting_form']);
                    broadcast(new ConversationUpdated($conversation));

                    $this->logInfo($m, 'Sent registration form to new customer', [
                        'conversation_id' => $conversation->id,
                        'phone' => $phone,
                    ]);

                    return $message;
                }

                if ($step === 'awaiting_form') {
                    // Driver sedang membalas form pendaftaran: Coba parse data
                    $parsed = $this->customerRegistrationService->parseRegistrationText($text);

                    if ($parsed !== null) {
                        // Ekstraksi berhasil: Buat record Customer baru dan Customer ID permanen
                        $newCustomer = $this->customerRegistrationService->registerCustomer($conversation, $parsed);

                        // Generate token Wi-Fi gratis 30 menit (zona waktu WIB)
                        $wifiToken = $this->wifiTokenService->generateToken($newCustomer->name, 30);

                        // Kirim welcome message resmi beserta kredensial Wi-Fi 30 menit
                        $welcomeText = $this->customerRegistrationService->buildWelcomeMessageText($newCustomer, $wifiToken);
                        $this->sendMessage($conversation->id, $welcomeText, 'bot');

                        broadcast(new ConversationUpdated($conversation));

                        $this->logInfo($m, 'Customer registered successfully and welcome Wi-Fi sent', [
                            'conversation_id' => $conversation->id,
                            'customer_id' => $newCustomer->id,
                            'customer_code' => $newCustomer->customer_code,
                        ]);

                        return $message;
                    }

                    // Jika balasan belum lengkap: Kirim pesan panduan pengisian
                    $guideText = $this->customerRegistrationService->getIncompleteGuideText();
                    $this->sendMessage($conversation->id, $guideText, 'bot');

                    $this->logInfo($m, 'Customer registration reply incomplete, sent guide text', [
                        'conversation_id' => $conversation->id,
                        'phone' => $phone,
                    ]);

                    return $message;
                }
            }

            // JIKA SUDAH TERDAFTAR: Jalankan alur percakapan normal AI dengan grace period 6 detik
            $this->logInfo($m, 'Dispatching GenerateAiReplyJob for registered customer message with 6s human grace period', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'customer_id' => $conversation->customer_id,
            ]);

            GenerateAiReplyJob::dispatch($conversation->id, $message->id)->delay(now()->addSeconds(6));
        }

        return $message;
    }

    /**
     * Format a conversation model into frontend-compatible payload.
     */
    protected function formatConversation(Conversation $c): array
    {
        $customer = $c->customer;
        $customerName = $customer?->name ?: ($c->name ?: $c->phone);
        $customerPhone = $customer?->phone ?: $c->phone;

        return [
            'id' => $c->id,
            'device_id' => $c->device_id,
            'chat_jid' => $c->chat_jid,
            'name' => $customerName,
            'phone' => $customerPhone,
            'customer_id' => $c->customer_id,
            'registration_step' => $c->registration_step ?? 'unregistered',
            'customer' => [
                'id' => $customer?->id ?? $c->id,
                'customer_code' => $customer?->customer_code,
                'name' => $customerName,
                'phone' => $customerPhone,
                'vehicle' => $customer?->vehicle ?? ($c->meta['vehicle'] ?? $c->meta['kendaraan'] ?? '-'),
                'driver_type' => $customer?->driver_type ?? ($c->meta['driver_type'] ?? '-'),
                'residential_area' => $customer?->residential_area ?? ($c->meta['residential_area'] ?? '-'),
                'primary_route' => $customer?->primary_route ?? ($c->meta['primary_route'] ?? '-'),
                'points_balance' => $customer?->points_balance ?? 0,
                'meterNo' => $customer?->customer_code ?? ($c->meta['meter_no'] ?? '-'),
                'segment' => $customer?->driver_type ? ucfirst(str_replace('_', ' ', $customer->driver_type)) : ($c->meta['segment'] ?? 'WhatsApp Contact'),
                'category' => $customer ? 'Registered EV Driver' : ($c->meta['category'] ?? 'General'),
                'status' => $customer?->status ?? 'Active',
            ],
            'unreadCount' => (int) $c->unread_count,
            'unread_count' => (int) $c->unread_count,
            'status' => $c->status,
            'handler' => $c->handler,
            'lastMessage' => $c->last_message ?? '',
            'last_message' => $c->last_message ?? '',
            'lastMessageTime' => $c->last_message_time?->toIso8601String(),
            'last_message_time' => $c->last_message_time?->toIso8601String(),
            'messages' => $c->relationLoaded('messages')
                ? $c->messages->map(fn (Message $m) => $this->formatMessage($m))->values()->all()
                : [],
        ];
    }

    /**
     * Format a message model into frontend-compatible payload.
     */
    protected function formatMessage(Message $m): array
    {
        return [
            'id' => $m->id,
            'conversation_id' => $m->conversation_id,
            'whatsapp_message_id' => $m->whatsapp_message_id,
            'sender' => $m->sender_type,
            'sender_type' => $m->sender_type,
            'is_from_me' => $m->is_from_me,
            'text' => $m->text,
            'time' => $m->sent_at?->toIso8601String(),
            'sent_at' => $m->sent_at?->toIso8601String(),
            'status' => $m->status,
        ];
    }

    /**
     * Handle typing presence indicator and auto-takeover for human operator.
     */
    public function handleTyping(string $conversationId, string $action): array
    {
        $m = __METHOD__;
        $conversation = Conversation::find($conversationId);

        if (! $conversation) {
            throw new NotFoundException("Conversation [{$conversationId}] not found.");
        }

        $normalizedAction = strtolower(trim($action));
        if (! in_array($normalizedAction, ['start', 'stop'], true)) {
            throw new BadRequestException("Invalid typing action [{$action}]. Supported: 'start', 'stop'.");
        }

        if ($normalizedAction === 'start') {
            // 1. Update database dan broadcast TERLEBIH DAHULU agar UI & status server berubah dalam < 5ms
            $conversation->update([
                'handler' => 'human',
                'status' => 'escalated',
            ]);

            broadcast(new ConversationUpdated($conversation));

            // 2. Jadwalkan auto-restore ke AI setelah 10 menit inaktivitas
            AutoRestoreAiHandlerJob::dispatch($conversation->id)->delay(now()->addMinutes(10));

            // 3. Kirim presence "sedang mengetik..." ke WhatsApp pelanggan (non-blocking jika error/timeout)
            try {
                $this->whatsappGateway->sendChatPresence(
                    $conversation->phone,
                    'start',
                    $conversation->device_id
                );
            } catch (Exception $e) {
                $this->logWarning($m, 'Could not send chat presence start to gateway: '.$e->getMessage());
            }

            $this->logInfo($m, 'Operator started typing, takeover to human enabled', [
                'conversation_id' => $conversation->id,
                'phone' => $conversation->phone,
            ]);
        } else {
            // action === 'stop'
            try {
                $this->whatsappGateway->sendChatPresence(
                    $conversation->phone,
                    'stop',
                    $conversation->device_id
                );
            } catch (Exception $e) {
                $this->logWarning($m, 'Could not send chat presence stop to gateway: '.$e->getMessage());
            }

            $this->logInfo($m, 'Operator stopped typing', [
                'conversation_id' => $conversation->id,
                'phone' => $conversation->phone,
            ]);
        }

        return [
            'conversation_id' => $conversation->id,
            'action' => $normalizedAction,
            'handler' => $conversation->handler,
            'status' => $conversation->status,
        ];
    }

    /**
     * Update conversation handler mode ('ai' or 'human') manually.
     */
    public function updateHandler(string $conversationId, string $handler): array
    {
        $m = __METHOD__;
        $conversation = Conversation::find($conversationId);

        if (! $conversation) {
            throw new NotFoundException("Conversation [{$conversationId}] not found.");
        }

        $normalizedHandler = strtolower(trim($handler));
        if (! in_array($normalizedHandler, ['ai', 'human'], true)) {
            throw new BadRequestException("Invalid handler mode [{$handler}]. Supported: 'ai', 'human'.");
        }

        $newStatus = $normalizedHandler === 'ai' ? 'all' : 'escalated';

        // 1. Update database dan broadcast TERLEBIH DAHULU agar status langsung responsif (< 5ms)
        $conversation->update([
            'handler' => $normalizedHandler,
            'status' => $newStatus,
        ]);

        broadcast(new ConversationUpdated($conversation));

        // 2. Jika mode diubah ke human, jadwalkan auto-restore ke AI setelah 10 menit
        if ($normalizedHandler === 'human') {
            AutoRestoreAiHandlerJob::dispatch($conversation->id)->delay(now()->addMinutes(10));
        }

        // 3. Jika dikembalikan ke AI, pastikan presence typing dimatikan di WhatsApp pelanggan
        if ($normalizedHandler === 'ai') {
            try {
                $this->whatsappGateway->sendChatPresence(
                    $conversation->phone,
                    'stop',
                    $conversation->device_id
                );
            } catch (Exception $e) {
                $this->logWarning($m, 'Could not send chat presence stop to gateway on AI restore: '.$e->getMessage());
            }
        }

        $this->logInfo($m, 'Conversation handler updated manually', [
            'conversation_id' => $conversation->id,
            'handler' => $normalizedHandler,
            'status' => $newStatus,
        ]);

        // Kembalikan metadata ringkas tanpa menimpa array messages di client
        return [
            'id' => $conversation->id,
            'handler' => $normalizedHandler,
            'status' => $newStatus,
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }
}
