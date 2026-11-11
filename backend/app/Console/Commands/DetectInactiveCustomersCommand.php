<?php

namespace App\Console\Commands;

use App\Jobs\Bot\SendRetentionMessageJob;
use App\Models\Bot\Customer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DetectInactiveCustomersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:detect-inactive {--days=8 : Batas jumlah hari inaktivitas (default 8 hari)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deteksi pelanggan rutin yang telah inaktif (>= 8 hari tidak melakukan charging) dan jadwalkan pesan retensi';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: 8);
        $this->info("Memulai deteksi pelanggan inaktif (threshold >= {$days} hari)...");

        $stats = self::runDetection($days);

        $this->info("Deteksi selesai:");
        $this->line("- Pelanggan inaktif terdeteksi: {$stats['detected_count']}");
        $this->line("- Status baru diubah menjadi 'inactive': {$stats['status_changed_count']}");
        $this->line("- Pesan retensi dijadwalkan: {$stats['retention_scheduled_count']}");

        return Command::SUCCESS;
    }

    /**
     * Logika deteksi inaktivitas terpusat yang dapat dipanggil dari CLI maupun Controller.
     *
     * @return array{
     *     threshold_days: int,
     *     cutoff_date: string,
     *     detected_count: int,
     *     status_changed_count: int,
     *     retention_scheduled_count: int,
     *     customers: array
     * }
     */
    public static function runDetection(int $thresholdDays = 8): array
    {
        $cutoff = Carbon::now()->subDays($thresholdDays);

        // Kriteria:
        // 1. Memiliki riwayat charging (last_charged_at IS NOT NULL)
        // 2. Charging terakhir sudah >= 8 hari yang lalu (last_charged_at <= cutoff)
        $candidates = Customer::whereNotNull('last_charged_at')
            ->where('last_charged_at', '<=', $cutoff)
            ->get();

        $statusChangedCount = 0;
        $retentionScheduledCount = 0;
        $details = [];

        foreach ($candidates as $customer) {
            $prevStatus = $customer->status;

            // 1. Update status ke inactive jika belum
            if ($customer->status !== 'inactive') {
                $customer->update([
                    'status' => 'inactive',
                    'inactive_detected_at' => now(),
                ]);
                $statusChangedCount++;
            }

            // 2. Evaluasi kelayakan pesan retensi:
            // - is_marketing_consent === true
            // - nomor HP valid
            // - belum pernah dikirimi pesan retensi, atau sudah lewat cooldown 14 hari
            $cooldownCutoff = Carbon::now()->subDays(14);
            $canSendRetention = $customer->is_marketing_consent
                && ! empty($customer->phone)
                && ($customer->last_retention_sent_at === null || $customer->last_retention_sent_at->lessThanOrEqualTo($cooldownCutoff));

            if ($canSendRetention) {
                // Rate-limiting bertahap: jeda 5 detik antarpesan
                SendRetentionMessageJob::dispatch($customer->id)
                    ->delay(now()->addSeconds($retentionScheduledCount * 5));

                $retentionScheduledCount++;
            }

            $details[] = [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'last_charged_at' => $customer->last_charged_at?->toIso8601String(),
                'status_changed' => $prevStatus !== 'inactive',
                'retention_scheduled' => $canSendRetention,
            ];
        }

        Log::info('[DetectInactiveCustomersCommand] Inactivity detection completed', [
            'threshold_days' => $thresholdDays,
            'cutoff' => $cutoff->toDateTimeString(),
            'detected_count' => $candidates->count(),
            'status_changed_count' => $statusChangedCount,
            'retention_scheduled_count' => $retentionScheduledCount,
        ]);

        return [
            'threshold_days' => $thresholdDays,
            'cutoff_date' => $cutoff->toDateTimeString(),
            'detected_count' => $candidates->count(),
            'status_changed_count' => $statusChangedCount,
            'retention_scheduled_count' => $retentionScheduledCount,
            'customers' => $details,
        ];
    }
}
