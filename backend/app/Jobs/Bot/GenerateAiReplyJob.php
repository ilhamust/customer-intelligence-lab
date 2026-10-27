<?php

namespace App\Jobs\Bot;

use App\Ai\Agents\CustomerSupportAgent;
use App\Ai\Guardrails\Dto\GuardrailContext;
use App\Ai\Guardrails\GuardrailManager;
use App\Events\Bot\MessageReceived;
use App\Models\Bot\Conversation;
use App\Models\Bot\Message;
use App\Service\Whatsapp\Contracts\WhatsappGatewayService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateAiReplyJob implements ShouldQueue
{
    use Queueable;

    /**
     * Waktu maksimum eksekusi job dalam detik (LLM + API Gateway).
     */
    public int $timeout = 120;

    /**
     * Jumlah percobaan maksimal jika terjadi kegagalan sementara.
     */
    public int $tries = 3;

    /**
     * Jeda waktu eksponensial (detik) antar percobaan ulang.
     */
    public array $backoff = [2, 5, 10];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $conversationId,
        public string $incomingMessageId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        WhatsappGatewayService $whatsappGateway,
        GuardrailManager $guardrailManager
    ): void {
        $logContext = [
            'conversation_id' => $this->conversationId,
            'message_id' => $this->incomingMessageId,
        ];

        // Ambil data percakapan beserta data pelanggan
        $conversation = Conversation::with(['customer'])->find($this->conversationId);
        if (! $conversation) {
            Log::warning('[GenerateAiReplyJob] Conversation not found, aborting', $logContext);

            return;
        }

        // Validasi handler: hanya balas jika handler aktif adalah 'ai'
        if ($conversation->handler !== 'ai') {
            Log::info('[GenerateAiReplyJob] Conversation handler is not "ai" (escalated to human), skipping auto-reply', $logContext);

            return;
        }

        // Ambil data pesan pelanggan yang memicu job ini
        $incomingMsg = Message::find($this->incomingMessageId);
        if (! $incomingMsg || $incomingMsg->is_from_me) {
            Log::info('[GenerateAiReplyJob] Incoming message is invalid or sent by operator, skipping', $logContext);

            return;
        }

        $promptText = trim((string) $incomingMsg->text);
        if ($promptText === '') {
            Log::info('[GenerateAiReplyJob] Message content is empty, skipping', $logContext);

            return;
        }

        // Siapkan konteks runtime guardrail
        $guardrailContext = new GuardrailContext(
            conversation: $conversation,
            incomingMessage: $incomingMsg,
            customer: $conversation->customer
        );

        // 1. Eksekusi Pipeline Input Guardrails (Pre-LLM)
        $inputResult = $guardrailManager->validateInput($promptText, $guardrailContext);

        // Jika input diblokir (misal: pesan terlalu panjang, spam, prompt injection)
        if ($inputResult->isBlocked()) {
            Log::warning('[GenerateAiReplyJob] Input blocked by guardrails', [
                ...$logContext,
                'reason' => $inputResult->getReason(),
                'telemetry' => $inputResult->getTelemetry(),
            ]);

            $this->dispatchGuardrailInterventionMessage(
                $conversation,
                $inputResult->getModifiedText(),
                'input_blocked',
                $inputResult->getReason() ?? 'input_blocked',
                $whatsappGateway,
                $logContext
            );

            return;
        }

        // Jika input membypass LLM (misal: permintaan bicara langsung dengan customer care)
        if ($inputResult->isBypassed()) {
            Log::info('[GenerateAiReplyJob] Input bypassed to human by guardrails', [
                ...$logContext,
                'reason' => $inputResult->getReason(),
                'telemetry' => $inputResult->getTelemetry(),
            ]);

            $this->dispatchGuardrailInterventionMessage(
                $conversation,
                $inputResult->getModifiedText(),
                'input_bypassed',
                $inputResult->getReason() ?? 'input_bypassed',
                $whatsappGateway,
                $logContext
            );

            return;
        }

        // Gunakan teks prompt yang telah disanitasi oleh input guardrails jika terdapat modifikasi
        $promptText = $inputResult->getModifiedText();

        Log::info('[GenerateAiReplyJob] Generating AI reply via CustomerSupportAgent', $logContext);

        // Kirim indikator "sedang mengetik..." (typing indicator) ke WhatsApp pelanggan
        $whatsappGateway->sendChatPresence($conversation->phone, 'start', $conversation->device_id);

        try {
            // Inisialisasi CustomerSupportAgent dengan histori percakapan
            $agent = new CustomerSupportAgent($conversation, $incomingMsg->id);
            $response = $agent->prompt($promptText);
            $rawReplyText = (string) $response->text;

            // 2. Eksekusi Pipeline Output Guardrails (Post-LLM)
            $outputResult = $guardrailManager->validateOutput($rawReplyText, $guardrailContext);
            $replyText = $outputResult->getModifiedText();

            if ($replyText === '') {
                Log::warning('[GenerateAiReplyJob] AI guardrail output resulted in empty response', $logContext);
                $whatsappGateway->sendChatPresence($conversation->phone, 'stop', $conversation->device_id);

                return;
            }

            Log::info('[GenerateAiReplyJob] AI response generated and sanitized successfully', [
                ...$logContext,
                'reply_length' => mb_strlen($replyText),
                'guardrail_action' => $outputResult->getAction(),
            ]);

            // Safety Guard: Validasi ulang sebelum pengiriman pesan ke WhatsApp
            // Mengecek apakah admin telah mulai mengetik / mengambil alih percakapan atau sudah membalas manual
            $conversation->refresh();
            $conversation->load('activeTicket');

            $humanAlreadyReplied = Message::where('conversation_id', $conversation->id)
                ->where('is_from_me', true)
                ->where('sender_type', 'human')
                ->where('created_at', '>=', $incomingMsg->created_at)
                ->exists();

            // Pengecualian: Jika percakapan dialihkan ke human karena AI baru saja membuat tiket eskalasi,
            // pesan notifikasi antrean resmi tetap harus dikirimkan ke WhatsApp pelanggan sebagai pesan penutup bot.
            $isTicketEscalationReply = $conversation->activeTicket !== null
                && $conversation->activeTicket->created_at?->diffInSeconds(now()) < 120;

            // Pastikan jika tiket antrean baru saja dibuat, balasan WhatsApp memuat informasi antrean resmi
            if ($isTicketEscalationReply && $conversation->activeTicket) {
                /** @var \App\Service\Bot\Contracts\TicketService $ticketService */
                $ticketService = app(\App\Service\Bot\Contracts\TicketService::class);
                $queuePos = $ticketService->calculateQueuePosition($conversation->activeTicket);
                $customerName = $conversation->customer?->name ?: ($conversation->name ?: 'Pelanggan');
                $officialQueueText = $ticketService->buildQueueNotificationText(
                    $queuePos,
                    $conversation->activeTicket->category,
                    $customerName
                );

                $containsQueueInfo = str_contains($replyText, 'antrean')
                    || str_contains($replyText, 'Antrean')
                    || str_contains($replyText, 'Customer Service')
                    || str_contains($replyText, 'Customer Care');

                // Jika respons AI belum memuat info antrean (misal AI cuma membalas sapaan 'Hai!'),
                // gunakan teks notifikasi antrean resmi agar pelanggan pasti mengetahui nomor antreannya.
                if (! $containsQueueInfo) {
                    $replyText = $officialQueueText;
                }
            }

            if ($humanAlreadyReplied || ($conversation->handler !== 'ai' && ! $isTicketEscalationReply)) {
                Log::info('[GenerateAiReplyJob] Safety guard triggered: Human takeover or human already replied, aborting AI reply', [
                    ...$logContext,
                    'handler' => $conversation->handler,
                    'human_already_replied' => $humanAlreadyReplied,
                    'is_ticket_escalation' => $isTicketEscalationReply,
                ]);

                // Jika admin sudah membalas, matikan presence.
                // Namun jika admin sedang mengambil alih (sedang mengetik balasan manual),
                // biarkan indikator "sedang mengetik..." tetap aktif di WhatsApp pelanggan.
                if ($humanAlreadyReplied) {
                    $whatsappGateway->sendChatPresence($conversation->phone, 'stop', $conversation->device_id);
                }

                return;
            }

            // Kirim balasan ke nomor pelanggan melalui WhatsApp Gateway
            $sent = $whatsappGateway->sendText(
                $conversation->phone,
                $replyText,
                $conversation->device_id
            );

            // Simpan pesan balasan bot ke database
            $botMessage = Message::create([
                'conversation_id' => $conversation->id,
                'whatsapp_message_id' => null,
                'sender_type' => 'bot',
                'is_from_me' => true,
                'text' => $replyText,
                'status' => $sent ? 'delivered' : 'failed',
                'sent_at' => now(),
                'meta' => [
                    'ai_generated' => true,
                    'provider' => $response->meta?->provider ?? 'ai',
                    'model' => $response->meta?->model ?? null,
                    'guardrails' => [
                        'input_action' => $inputResult->getAction(),
                        'output_action' => $outputResult->getAction(),
                        'output_reason' => $outputResult->getReason(),
                    ],
                ],
            ]);

            // Update status percakapan terakhir
            $conversation->update([
                'last_message' => $replyText,
                'last_message_time' => $botMessage->sent_at,
                'unread_count' => 0,
            ]);

            Log::info('[GenerateAiReplyJob] Bot reply saved and conversation updated', [
                ...$logContext,
                'bot_message_id' => $botMessage->id,
                'gateway_status' => $sent ? 'delivered' : 'failed',
            ]);

            // Broadcast event agar tampilan chat di web dashboard terupdate secara realtime
            try {
                broadcast(new MessageReceived($botMessage, $conversation));
            } catch (\Throwable $e) {
                Log::warning('[GenerateAiReplyJob] Failed broadcasting bot reply to WebSocket: '.$e->getMessage(), [
                    ...$logContext,
                    'bot_message_id' => $botMessage->id,
                ]);
            }

        } catch (Exception $e) {
            // Pastikan presence typing dimatikan jika terjadi error
            $whatsappGateway->sendChatPresence($conversation->phone, 'stop', $conversation->device_id);

            Log::error('[GenerateAiReplyJob] Failed to generate or send AI reply: '.$e->getMessage(), [
                ...$logContext,
                'exception' => $e,
            ]);

            throw $e; // Throw agar queue worker dapat me-retry sesuai konfigurasi $tries & $backoff
        }
    }

    /**
     * Kirim pesan notifikasi intervensi guardrail (blocked atau bypassed) ke WhatsApp pelanggan
     * serta simpan sebagai pesan bot di basis data.
     */
    protected function dispatchGuardrailInterventionMessage(
        Conversation $conversation,
        string $replyText,
        string $interventionType,
        string $reason,
        WhatsappGatewayService $whatsappGateway,
        array $logContext
    ): void {
        $replyText = trim($replyText);
        if ($replyText === '') {
            return;
        }

        $sent = $whatsappGateway->sendText(
            $conversation->phone,
            $replyText,
            $conversation->device_id
        );

        $botMessage = Message::create([
            'conversation_id' => $conversation->id,
            'whatsapp_message_id' => null,
            'sender_type' => 'bot',
            'is_from_me' => true,
            'text' => $replyText,
            'status' => $sent ? 'delivered' : 'failed',
            'sent_at' => now(),
            'meta' => [
                'ai_generated' => false,
                'guardrail_intervention' => $interventionType,
                'guardrail_reason' => $reason,
            ],
        ]);

        $conversation->update([
            'last_message' => $replyText,
            'last_message_time' => $botMessage->sent_at,
            'unread_count' => 0,
        ]);

        try {
            broadcast(new MessageReceived($botMessage, $conversation));
        } catch (\Throwable $e) {
            Log::warning('[GenerateAiReplyJob] Failed broadcasting guardrail message to WebSocket: '.$e->getMessage(), $logContext);
        }
    }
}
