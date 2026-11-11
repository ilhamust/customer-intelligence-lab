<?php

namespace App\Jobs\Bot;

use App\Models\Bot\Conversation;
use App\Models\Bot\Customer;
use App\Service\Bot\Contracts\ConversationService;
use App\Service\Loyalty\Contracts\LoyaltyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendRetentionMessageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $customerId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        ConversationService $conversationService,
        LoyaltyService $loyaltyService
    ): void {
        $customer = Customer::find($this->customerId);

        if (! $customer) {
            Log::warning('[SendRetentionMessageJob] Customer not found', ['customer_id' => $this->customerId]);
            return;
        }

        // Validasi kelayakan penerimaan pesan retensi
        if ($customer->status !== 'inactive' || ! $customer->is_marketing_consent || empty($customer->phone)) {
            Log::info('[SendRetentionMessageJob] Customer not eligible for retention message', [
                'customer_id' => $customer->id,
                'status' => $customer->status,
                'is_marketing_consent' => $customer->is_marketing_consent,
                'has_phone' => ! empty($customer->phone),
            ]);
            return;
        }

        $tier = $loyaltyService->resolveCustomerTier((int) $customer->points_balance);
        $vehicleInfo = ! empty($customer->vehicle) && $customer->vehicle !== '-' 
            ? $customer->vehicle 
            : 'kendaraan listrik Anda';

        $message = <<<TXT
Halo Kak *{$customer->name}*! 👋⚡

Semoga aktivitas Kakak bersama *{$vehicleInfo}* berjalan lancar hari ini.
Kami melihat Kakak sudah beberapa hari belum sempat mampir untuk pengisian daya di stasiun Jarvis.

Sekadar mengingatkan, Kakak saat ini memiliki:
💼 Saldo: *{$customer->points_balance} Jarvis Points*
🎖️ Status Tier: *{$tier}*

Stasiun Jarvis Sunset Road tetap beroperasi *24 Jam* dengan pengisian cepat (DC Fast Charging), akses Wi-Fi gratis, dan lounge nyaman untuk istirahat sejenak ☕

Sampai jumpa di stasiun Jarvis ya Kak! 🚗⚡

_Ketik *MENU* untuk melihat panduan bantuan. Ketik *UNREG* jika tidak ingin menerima pesan pengingat promo ini._
TXT;

        try {
            $phone = $customer->phone;
            $chatJid = "{$phone}@s.whatsapp.net";

            // Pastikan entitas percakapan tercatat di database agar tersinkronisasi ke Back Office
            $conversation = Conversation::firstOrCreate(
                ['phone' => $phone],
                [
                    'device_id' => 'default',
                    'chat_jid' => $chatJid,
                    'name' => $customer->name,
                    'customer_id' => $customer->id,
                    'handler' => 'ai',
                    'status' => 'all',
                ]
            );

            // Kirim pesan retensi via bot:
            // 1. Mengirim ke gateway WhatsApp
            // 2. Menyimpan baris pesan ke tabel 'messages'
            // 3. Mem-broadcast ke WebSocket Reverb agar realtime muncul di UI Back Office
            $conversationService->sendMessage($conversation->id, $message, 'bot');

            // Tandai waktu pengiriman retensi terakhir untuk mencegah spam berulang
            $customer->update([
                'last_retention_sent_at' => now(),
            ]);

            Log::info('[SendRetentionMessageJob] Retention message sent and recorded successfully', [
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'conversation_id' => $conversation->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[SendRetentionMessageJob] Failed to send retention message', [
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
