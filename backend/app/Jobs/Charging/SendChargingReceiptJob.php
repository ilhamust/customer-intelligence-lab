<?php

namespace App\Jobs\Charging;

use App\Models\Bot\Conversation;
use App\Models\Charging\ChargingTransaction;
use App\Service\Bot\Contracts\ConversationService;
use App\Service\Loyalty\Contracts\LoyaltyService;
use App\Service\Whatsapp\Contracts\WhatsappGatewayService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendChargingReceiptJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $transactionId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        WhatsappGatewayService $whatsappGateway,
        LoyaltyService $loyaltyService,
        ConversationService $conversationService
    ): void {
        $transaction = ChargingTransaction::with(['customer', 'pointsLedger'])->find($this->transactionId);

        if (! $transaction) {
            Log::warning('[SendChargingReceiptJob] Transaction not found', ['transaction_id' => $this->transactionId]);
            return;
        }

        if (empty($transaction->phone)) {
            Log::info('[SendChargingReceiptJob] Transaction does not have a phone number, skipping receipt dispatch', [
                'transaction_id' => $transaction->id,
                'external_id' => $transaction->external_id,
            ]);
            return;
        }

        $finishedAt = $transaction->finished_at ? Carbon::parse($transaction->finished_at)->translatedFormat('d M Y, H:i') . ' WITA' : now()->toDateTimeString();
        $amountFormatted = number_format((float) $transaction->amount, 0, ',', '.');
        $kwhFormatted = number_format((float) $transaction->kwh, 2, ',', '.');

        if ($transaction->customer_id && $transaction->customer) {
            // Skenario 1: Member Terdaftar (Kuitansi Lengkap + Rincian Poin & Tier)
            $customer = $transaction->customer;
            $tier = $loyaltyService->resolveCustomerTier((int) $customer->points_balance);
            $chargerInfo = $transaction->charger_type ? "{$transaction->charger_code} ({$transaction->charger_type})" : $transaction->charger_code;
            $durationInfo = $transaction->duration_minutes ? "{$transaction->duration_minutes} Menit" : '-';

            $message = <<<TXT
*KUITANSI RESMI PENGISIAN DAYA JARVIS EV* ⚡🧾
ID Transaksi: #{$transaction->external_id}
Waktu Selesai: {$finishedAt}

Detail Pengisian:
• Stasiun: {$transaction->station_name} ({$transaction->station_code})
• Charger: {$chargerInfo}
• Total Energi: {$kwhFormatted} kWh
• Durasi: {$durationInfo}
• Total Biaya: Rp {$amountFormatted}

Perolehan Poin Loyalitas:
⭐ Poin Didapat: +{$transaction->points_awarded} Jarvis Points
💼 Total Saldo Poin: {$customer->points_balance} Poin
🎖️ Status Tier: {$tier}

Terima kasih telah memilih stasiun pengisian daya Jarvis EV! 🚗⚡
TXT;
        } else {
            // Skenario 2: Non-Member (Kuitansi Ringkas + Ajakan Registrasi & Klaim Poin)
            $potentialPoints = $loyaltyService->calculatePoints((float) $transaction->kwh, (float) $transaction->amount);

            $message = <<<TXT
*KUITANSI PENGISIAN DAYA JARVIS EV* ⚡🧾
ID Transaksi: #{$transaction->external_id}
Waktu Selesai: {$finishedAt}

Detail Pengisian:
• Stasiun: {$transaction->station_name}
• Charger: {$transaction->charger_code}
• Total Energi: {$kwhFormatted} kWh
• Total Biaya: Rp {$amountFormatted}

🎁 *Klaim {$potentialPoints} Jarvis Points Anda!*
Sesi pengisian ini berhak mendapatkan *{$potentialPoints} Jarvis Points*.
Ketik *DAFTAR* untuk membuat Jarvis ID gratis & langsung klaim akumulasi poin Anda + dapatkan akses Wi-Fi 30 menit! ⚡
TXT;
        }

        try {
            $phone = $transaction->phone;
            $chatJid = "{$phone}@s.whatsapp.net";

            // Pastikan entitas percakapan tercatat di database agar muncul di UI Frontend
            $conversation = Conversation::firstOrCreate(
                ['phone' => $phone],
                [
                    'device_id' => 'default',
                    'chat_jid' => $chatJid,
                    'name' => $transaction->customer?->name ?? ($transaction->plate_number ? "Driver {$transaction->plate_number}" : "Pelanggan {$phone}"),
                    'customer_id' => $transaction->customer_id,
                    'handler' => 'ai',
                    'status' => 'all',
                ]
            );

            // Kirim pesan kuitansi melalui conversationService:
            // 1. Mengirim ke gateway WhatsApp
            // 2. Menyimpan baris pesan ke tabel 'messages'
            // 3. Mem-broadcast event MessageReceived ke WebSocket Reverb agar realtime muncul di Frontend
            $conversationService->sendMessage($conversation->id, $message, 'bot');

            Log::info('[SendChargingReceiptJob] Charging receipt dispatched and persisted to conversation successfully', [
                'transaction_id' => $transaction->id,
                'external_id' => $transaction->external_id,
                'phone' => $transaction->phone,
                'conversation_id' => $conversation->id,
                'is_member' => $transaction->customer_id !== null,
            ]);
        } catch (\Throwable $e) {
            Log::error('[SendChargingReceiptJob] Failed to dispatch charging receipt', [
                'transaction_id' => $transaction->id,
                'phone' => $transaction->phone,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
