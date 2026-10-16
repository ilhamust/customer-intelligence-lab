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
    public int $timeout = 60;

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

        // 1. Ambil data percakapan
        $conversation = Conversation::find($this->conversationId);
        if (! $conversation) {
            Log::warning('[GenerateAiReplyJob] Conversation not found, aborting', $logContext);

            return;
        }

        // 2. Validasi handler: hanya balas jika handler aktif adalah 'ai'
        if ($conversation->handler !== 'ai') {
            Log::info('[GenerateAiReplyJob] Conversation handler is not "ai" (escalated to human), skipping auto-reply', $logContext);

            return;
        }

        // 3. Ambil data pesan pelanggan yang memicu job ini
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

        try {
            // 4. Inisialisasi CustomerSupportAgent dengan histori percakapan
            $agent = new CustomerSupportAgent($conversation, $incomingMsg->id);
            $response = $agent->prompt($promptText);
            $replyText = trim((string) $response->text);

            if ($replyText === '') {
                Log::warning('[GenerateAiReplyJob] AI agent returned an empty response', $logContext);

                return;
            }

            Log::info('[GenerateAiReplyJob] AI response generated successfully', [
                ...$logContext,
                'reply_length' => mb_strlen($replyText),
            ]);

            // 5. Kirim balasan ke nomor pelanggan melalui WhatsApp Gateway
            $sent = $whatsappGateway->sendText(
                $conversation->phone,
                $replyText,
                $conversation->device_id
            );

            // 6. Simpan pesan balasan bot ke database
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
                    'provider' => $response->meta['provider'] ?? 'ai',
                ],
            ]);

            // 7. Update status percakapan terakhir
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

            // 8. Broadcast event agar tampilan chat di web dashboard terupdate secara realtime
            broadcast(new MessageReceived($botMessage, $conversation));

        } catch (Exception $e) {
            Log::error('[GenerateAiReplyJob] Failed to generate or send AI reply: '.$e->getMessage(), [
                ...$logContext,
                'exception' => $e,
            ]);

            throw $e; // Throw agar queue worker dapat me-retry sesuai konfigurasi $tries & $backoff
        }
    }
}
