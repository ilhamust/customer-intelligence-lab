<?php

namespace App\Jobs\Bot;

use App\Events\Bot\ConversationUpdated;
use App\Models\Bot\Conversation;
use App\Models\Bot\Message;
use App\Service\Whatsapp\Contracts\WhatsappGatewayService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AutoRestoreAiHandlerJob implements ShouldQueue
{
    use Queueable;

    /**
     * Waktu timeout job dalam detik.
     */
    public int $timeout = 30;

    /**
     * Jumlah percobaan maksimal jika terjadi kegagalan.
     */
    public int $tries = 2;

    /**
     * Ambang batas inaktivitas dalam menit.
     */
    public int $inactivityMinutes;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $conversationId,
        int $inactivityMinutes = 10
    ) {
        $this->inactivityMinutes = $inactivityMinutes;
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsappGatewayService $whatsappGateway): void
    {
        $logContext = [
            'conversation_id' => $this->conversationId,
            'inactivity_threshold_minutes' => $this->inactivityMinutes,
        ];

        // Ambil data percakapan terbaru
        $conversation = Conversation::find($this->conversationId);
        if (! $conversation) {
            Log::info('[AutoRestoreAiHandlerJob] Conversation not found, aborting', $logContext);

            return;
        }

        // Validasi handler: hanya restore jika saat ini masih di-handle oleh 'human'
        if ($conversation->handler !== 'human') {
            Log::info('[AutoRestoreAiHandlerJob] Handler is not "human" (already AI or other), skipping restore', [
                ...$logContext,
                'current_handler' => $conversation->handler,
            ]);

            return;
        }

        // Cek aktivitas pesan human terakhir
        $latestHumanMessage = Message::where('conversation_id', $conversation->id)
            ->where('is_from_me', true)
            ->where('sender_type', 'human')
            ->latest('sent_at')
            ->first();

        $cutoffTime = now()->subMinutes($this->inactivityMinutes);

        // Jika ada pesan human yang dikirim kurang dari 10 menit yang lalu, artinya operator masih aktif berinteraksi
        if ($latestHumanMessage && $latestHumanMessage->sent_at && Carbon::parse($latestHumanMessage->sent_at)->greaterThan($cutoffTime)) {
            Log::info('[AutoRestoreAiHandlerJob] Human agent was active recently, skipping auto-restore', [
                ...$logContext,
                'latest_human_message_at' => $latestHumanMessage->sent_at,
                'cutoff_time' => $cutoffTime->toIso8601String(),
            ]);

            return;
        }

        // Inaktivitas >= 10 menit terpenuhi: kembalikan handler ke 'ai'
        $conversation->update([
            'handler' => 'ai',
            'status' => 'all',
        ]);

        // Pastikan typing presence dimatikan di WhatsApp pelanggan
        $whatsappGateway->sendChatPresence($conversation->phone, 'stop', $conversation->device_id);

        Log::info('[AutoRestoreAiHandlerJob] Conversation handler successfully auto-restored to "ai" after inactivity', [
            ...$logContext,
            'last_message_time' => $conversation->last_message_time,
        ]);

        // Broadcast event agar tampilan chat dan status toggle di web dashboard terupdate secara realtime
        broadcast(new ConversationUpdated($conversation));
    }
}
