<?php

namespace App\Jobs\Alerts;

use App\Events\Alerts\UrgentAlertTriggered;
use App\Models\Alerts\UrgentAlert;
use App\Models\Bot\WhatsappChannel;
use App\Service\Staff\Contracts\StationStaffService;
use App\Service\Whatsapp\Contracts\WhatsappGatewayService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DispatchUrgentAlertJob implements ShouldQueue
{
    use Queueable;

    /**
     * Waktu maksimum eksekusi job dalam detik.
     */
    public int $timeout = 60;

    /**
     * Jumlah percobaan maksimal jika terjadi kegagalan jaringan sementara.
     */
    public int $tries = 3;

    /**
     * Jeda waktu antar percobaan ulang (detik).
     */
    public array $backoff = [2, 5, 10];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $urgentAlertId
    ) {}

    /**
     * Execute the job: Broadcast WhatsApp emergency alert to all active & on-duty station staff.
     */
    public function handle(
        StationStaffService $staffService,
        WhatsappGatewayService $whatsappGateway
    ): void {
        $alert = UrgentAlert::with(['customer', 'conversation', 'ticket'])->find($this->urgentAlertId);

        if (! $alert) {
            Log::warning('[DispatchUrgentAlertJob] UrgentAlert record not found, skipping dispatch', [
                'urgent_alert_id' => $this->urgentAlertId,
            ]);

            return;
        }

        // Ambil seluruh staf lapangan yang aktif (is_active = true) dan sedang bertugas (is_on_duty = true)
        $onDutyStaff = $staffService->getActiveOnDutyStaff();

        $deviceId = $alert->conversation?->device_id ?: $this->resolveActiveDeviceId();
        $alertMessage = $this->buildStaffEmergencyMessage($alert);

        $dispatchedRecipients = [];

        foreach ($onDutyStaff as $staff) {
            try {
                $whatsappGateway->sendText(
                    $staff->phone,
                    $alertMessage,
                    $deviceId
                );

                $dispatchedRecipients[] = $staff->phone;
            } catch (\Throwable $e) {
                Log::error('[DispatchUrgentAlertJob] Failed sending WhatsApp alert to staff member: ' . $e->getMessage(), [
                    'alert_code' => $alert->alert_code,
                    'staff_id' => $staff->id,
                    'staff_phone' => $staff->phone,
                ]);
            }
        }

        // Perbarui daftar penerima dan ubah status menjadi dispatched
        $alert->update([
            'dispatched_recipients' => array_values(array_unique($dispatchedRecipients)),
            'status' => 'dispatched',
        ]);

        Log::info('[DispatchUrgentAlertJob] Urgent alert dispatched to on-duty staff', [
            'alert_id' => $alert->id,
            'alert_code' => $alert->alert_code,
            'recipients_count' => count($dispatchedRecipients),
            'recipients' => $dispatchedRecipients,
        ]);

        try {
            broadcast(new UrgentAlertTriggered($alert->fresh(['customer', 'conversation', 'ticket']), 'dispatched'));
        } catch (\Throwable $e) {
            Log::warning('[DispatchUrgentAlertJob] Failed broadcasting UrgentAlertTriggered: ' . $e->getMessage(), [
                'alert_id' => $alert->id,
            ]);
        }
    }

    /**
     * Susun pesan darurat resmi untuk staf lapangan sesuai spesifikasi bisnis (project_summary.md Bagian 5).
     */
    public function buildStaffEmergencyMessage(UrgentAlert $alert): string
    {
        $customer = $alert->customer ?: $alert->conversation?->customer;
        $customerName = $customer?->name ?: ($alert->conversation?->name ?: 'Pelanggan');
        $customerPhone = $customer?->phone ?: ($alert->conversation?->phone ?: '-');
        $vehicle = $customer?->vehicle ?: ($alert->conversation?->meta['vehicle'] ?? '-');
        $stationLocation = $customer?->primary_route ?: ($customer?->residential_area ?: 'Stasiun SPKLU Jarvis');
        $ticketCode = $alert->ticket?->ticket_code ?: '-';
        $categoryLabel = $this->getIncidentCategoryLabel($alert->incident_category);
        $timeWib = ($alert->created_at ?: now())->timezone('Asia/Jakarta')->format('H:i');

        return <<<TEXT
🚨 *URGENT CUSTOMER ALERT: {$stationLocation}*
Kode Alert: *{$alert->alert_code}*
Tiket Prioritas: *{$ticketCode}* (Level 3 - URGENT)
Kategori: *{$categoryLabel}*
Customer: *{$customerName}* ({$customerPhone})
Kendaraan: {$vehicle}
Masalah: "{$alert->raw_message}"
Waktu: {$timeWib} WIB
Status: *Customer menunggu bantuan darurat segera*

Mohon petugas terdekat segera menuju titik charger atau menghubungi pelanggan sekarang! ⚡
TEXT;
    }

    /**
     * Terjemahkan kode kategori insiden ke label Bahasa Indonesia yang jelas.
     */
    protected function getIncidentCategoryLabel(string $category): string
    {
        return match ($category) {
            'safety_hazard' => 'Bahaya Keselamatan / Korsleting / Api / Asap',
            'trapped_cable' => 'Konektor / Kabel Charger Terkunci di Mobil',
            'power_outage' => 'Charger / Listrik Mati Total Mendadak',
            'hardware_failure' => 'Kerusakan Fisik Perangkat Charger',
            default => 'Insiden Darurat Stasiun SPKLU',
        };
    }

    /**
     * Cari device_id WhatsApp yang sedang aktif/terhubung jika percakapan tidak menyimpan device_id.
     */
    protected function resolveActiveDeviceId(): ?string
    {
        $channel = WhatsappChannel::where('status', 'connected')->first()
            ?? WhatsappChannel::where('is_active', true)->first()
            ?? WhatsappChannel::first();

        return $channel?->device_id;
    }
}

