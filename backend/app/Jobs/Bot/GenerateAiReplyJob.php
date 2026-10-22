<?php

namespace App\Jobs\Bot;

use App\Ai\Agents\CustomerSupportAgent;
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
    public function handle(WhatsappGatewayService $whatsappGateway): void
    {
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

        Log::info('[GenerateAiReplyJob] Generating AI reply via CustomerSupportAgent', $logContext);

        // Kirim indikator "sedang mengetik..." (typing indicator) ke WhatsApp pelanggan
        $whatsappGateway->sendChatPresence($conversation->phone, 'start', $conversation->device_id);

        try {
            // Inisialisasi CustomerSupportAgent dengan histori percakapan
            $agent = new CustomerSupportAgent($conversation, $incomingMsg->id);
            $response = $agent->prompt($promptText);
            $replyText = CustomerSupportAgent::formatForWhatsApp((string) $response->text);

            if ($replyText === '') {
                Log::warning('[GenerateAiReplyJob] AI agent returned an empty response', $logContext);
                $whatsappGateway->sendChatPresence($conversation->phone, 'stop', $conversation->device_id);

                return;
            }

            Log::info('[GenerateAiReplyJob] AI response generated successfully', [
                ...$logContext,
                'reply_length' => mb_strlen($replyText),
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
            broadcast(new MessageReceived($botMessage, $conversation));

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
}
