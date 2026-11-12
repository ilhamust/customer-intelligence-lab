<?php

namespace App\Ai\Guardrails\Input;

use App\Ai\Guardrails\Contracts\InputGuardrailInterface;
use App\Ai\Guardrails\Dto\GuardrailContext;
use App\Ai\Guardrails\Dto\GuardrailResult;
use App\Events\Alerts\UrgentAlertTriggered;
use App\Events\Bot\ConversationUpdated;
use App\Jobs\Alerts\DispatchUrgentAlertJob;
use App\Models\Alerts\UrgentAlert;
use App\Service\Bot\Contracts\TicketService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmergencyInputGuard implements InputGuardrailInterface
{
    /**
     * Pola regex komprehensif yang dikelompokkan berdasarkan kategori insiden darurat (Level 3 Urgent).
     *
     * @var array<string, list<string>>
     */
    protected array $categoryPatterns = [
        'safety_hazard' => [
            '/\b(?:korslet(?:ing)?|konslet(?:ing)?|meledak|ledakan|terbakar|kebakaran|berasap|nyetrum|kesetrum|setrum|sengatan)\b/ui',
            '/(?:charger\s+berasap|keluar\s+(?:api|asap)|muncul\s+(?:api|asap)|ada\s+(?:api|asap)|percikan\s+api)/ui',
            '/\b(?:api(?!\s*(?:key|token|endpoint|doc|rest|gateway))|asap)\b/ui',
        ],
        'trapped_cable' => [
            '/(?:kabel\s+macet|colokan\s+nyangkut|konektor\s+nyangkut|tidak\s+bisa\s+dicabut|gak\s+bisa\s+dicabut|ga\s+bisa\s+dicabut)/ui',
            '/(?:(?:kabel|colokan|konektor|gun|plug)\s+(?:charger\s+)?(?:macet|nyangkut|terkunci|ketahan|susah\s+dicabut))/ui',
            '/(?:terjebak\s+di\s+(?:stasiun|charger|spklu)|mobil\s+terkunci\s+charger)/ui',
        ],
        'power_outage' => [
            '/\b(?:mati\s+total|padam\s+total)\b/ui',
            '/(?:(?:charger|mesin|listrik|spklu)\s+(?:mati\s+total|padam|mati\s+mendadak|berhenti\s+tiba[\s-]*tiba))/ui',
        ],
        'hardware_failure' => [
            '/(?:(?:charger|konektor|layar|mesin)\s+(?:rusak\s+parah|pecah|patah|hancur|error\s+parah))/ui',
        ],
        'general_emergency' => [
            '/\b(?:tolong\s+darurat|keadaan\s+darurat|kondisi\s+darurat|emergency|bahaya)\b/ui',
        ],
    ];

    public function __construct(
        protected bool $enabled = true,
        array $customPatterns = []
    ) {
        if (! empty($customPatterns)) {
            $this->categoryPatterns['general_emergency'] = array_merge(
                $this->categoryPatterns['general_emergency'],
                $customPatterns
            );
        }
    }

    /**
     * Nama unik guardrail.
     */
    public function name(): string
    {
        return 'emergency_input_guard';
    }

    /**
     * Status apakah guardrail ini aktif.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Deteksi cepat (pure helper) apakah sebuah teks mengandung indikasi darurat fisik/keselamatan.
     *
     * @return array{category: string, matched: string}|null
     */
    public function detectEmergency(string $text): ?array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        foreach ($this->categoryPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $trimmed, $matches)) {
                    return [
                        'category' => $category,
                        'matched' => $matches[0] ?? $pattern,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Evaluasi pesan pelanggan secara deterministik (< 10ms) sebelum memanggil LLM.
     * Jika terdeteksi bahaya fisik/keselamatan:
     * 1. Catat insiden ke tabel urgent_alerts (ALT-XXXX).
     * 2. Buat tiket Level 3 (priority = urgent) via TicketService.
     * 3. Alihkan handler percakapan ke 'human' (status = 'all').
     * 4. Dispatch DispatchUrgentAlertJob ke seluruh staf lapangan yang On-Duty.
     * 5. Kembalikan pesan instruksi keselamatan resmi ke pelanggan (Bypass LLM).
     */
    public function validateInput(string $text, GuardrailContext $context): GuardrailResult
    {
        $trimmed = trim($text);
        $detected = $this->detectEmergency($trimmed);

        if ($detected === null) {
            return GuardrailResult::allow($trimmed);
        }

        $category = $detected['category'];
        $matchedKeyword = $detected['matched'];
        $customerName = $context->getCustomerName();

        Log::critical('[EmergencyInputGuard] Physical/Safety emergency detected! Bypassing LLM immediately.', [
            'incident_category' => $category,
            'matched_keyword' => $matchedKeyword,
            'conversation_id' => $context->getConversationId(),
            'customer_name' => $customerName,
            'customer_phone' => $context->getCustomerPhone(),
        ]);

        if ($context->conversation && $context->conversation->id) {
            $conversation = $context->conversation;

            /** @var array{alert: UrgentAlert, ticket: \App\Models\Bot\Ticket} $records */
            $records = DB::transaction(function () use ($conversation, $context, $category, $matchedKeyword, $trimmed) {
                /** @var TicketService $ticketService */
                $ticketService = app(TicketService::class);

                // Buat tiket Level 3 dengan prioritas urgent
                $ticket = $ticketService->createTicket(
                    conversation: $conversation,
                    category: $category,
                    priority: 'urgent',
                    notes: "Eskalasi Darurat Level 3 otomatis via EmergencyInputGuard (Kata kunci: {$matchedKeyword})",
                    metadata: [
                        'issue_summary' => $trimmed,
                        'escalated_by' => 'emergency_input_guard',
                        'incident_category' => $category,
                        'matched_keyword' => $matchedKeyword,
                    ]
                );

                // Jika percakapan sebelumnya sudah punya tiket reguler aktif, naikkan prioritasnya ke urgent
                if ($ticket->priority !== 'urgent') {
                    $ticket->update([
                        'priority' => 'urgent',
                        'category' => $category,
                    ]);
                }

                // Alihkan handler percakapan ke human (status = 'all') sesuai spesifikasi
                $conversation->update([
                    'handler' => 'human',
                    'status' => 'all',
                ]);

                // Simpan jejak audit insiden ke tabel urgent_alerts
                $alert = UrgentAlert::create([
                    'alert_code' => UrgentAlert::generateAlertCode(),
                    'conversation_id' => $conversation->id,
                    'customer_id' => $context->customer?->id ?? $conversation->customer_id,
                    'ticket_id' => $ticket->id,
                    'incident_category' => $category,
                    'raw_message' => $trimmed,
                    'dispatched_recipients' => [],
                    'status' => 'triggered',
                ]);

                return [
                    'alert' => $alert,
                    'ticket' => $ticket,
                ];
            });

            $alert = $records['alert'];
            $ticket = $records['ticket'];

            // Broadcast perubahan ke dashboard secara real-time
            try {
                broadcast(new ConversationUpdated($conversation->fresh()));
                broadcast(new UrgentAlertTriggered($alert, 'triggered'));
            } catch (\Throwable $e) {
                Log::warning('[EmergencyInputGuard] Failed broadcasting emergency events: ' . $e->getMessage());
            }

            // Dispatch job pengiriman pesan darurat WhatsApp ke seluruh staf lapangan yang On-Duty
            DispatchUrgentAlertJob::dispatch($alert->id);

            $safetyResponse = $this->buildSafetyInstructionMessage(
                customerName: $customerName,
                alertCode: $alert->alert_code,
                ticketCode: $ticket->ticket_code,
                category: $category
            );

            return GuardrailResult::bypass(
                bypassResponse: $safetyResponse,
                reason: 'emergency_safety_escalation',
                action: 'escalate_urgent_level_3',
                telemetry: [
                    'alert_id' => $alert->id,
                    'alert_code' => $alert->alert_code,
                    'ticket_id' => $ticket->id,
                    'ticket_code' => $ticket->ticket_code,
                    'incident_category' => $category,
                    'matched_keyword' => $matchedKeyword,
                ]
            );
        }

        // Fallback jika dipanggil tanpa objek Conversation di database (misal pada unit test murni)
        $fallbackResponse = $this->buildSafetyInstructionMessage(
            customerName: $customerName,
            alertCode: 'ALT-0000',
            ticketCode: 'TKT-0000',
            category: $category
        );

        return GuardrailResult::bypass(
            bypassResponse: $fallbackResponse,
            reason: 'emergency_safety_escalation',
            action: 'escalate_urgent_level_3',
            telemetry: [
                'incident_category' => $category,
                'matched_keyword' => $matchedKeyword,
            ]
        );
    }

    /**
     * Bangun pesan instruksi keselamatan resmi untuk pelanggan.
     */
    public function buildSafetyInstructionMessage(
        string $customerName,
        string $alertCode,
        string $ticketCode,
        string $category
    ): string {
        $specificInstruction = match ($category) {
            'safety_hazard' => "1. *JANGAN SENTUH* unit charger atau kabel jika terlihat asap, percikan api, atau indikasi korsleting.\n2. Segera tekan tombol merah *EMERGENCY STOP* pada bodi mesin charger jika aman dijangkau.\n3. Menjauh minimal *5 meter* dari titik charger demi keselamatan Kakak.",
            'trapped_cable' => "1. *JANGAN MENARIK PAKSA* kabel atau konektor charger untuk menghindari kerusakan port kendaraan atau sengatan listrik.\n2. Pastikan kendaraan dalam posisi *OFF / Park (P)* dan coba tekan tombol *Unlock Charge Port* dari kunci/layar mobil.\n3. Tetap tenang, teknisi kami segera memandu pelepasan kunci darurat (*manual release*).",
            'power_outage' => "1. *JANGAN MENCABUT PAKSA* konektor saat sistem baru saja mati mendadak.\n2. Pastikan posisi transmisi mobil di *Park (P)* dan tunggu 1-2 menit hingga kunci pengaman konektor terbuka otomatis.",
            default => "1. *UTAMAKAN KESELAMATAN* dan jangan memaksakan penggunaan mesin charger yang bermasalah.\n2. Jika situasi membahayakan, tekan tombol merah *EMERGENCY STOP* dan menjauh dari perangkat.",
        };

        return <<<TEXT
🚨 *PERINGATAN & INSTRUKSI KESELAMATAN DARURAT JARVIS*

Halo Kak *{$customerName}*, sistem keamanan 24/7 kami mendeteksi laporan kondisi darurat dari pesan Kakak.

*Mohon ikuti langkah keselamatan berikut sekarang:*
{$specificInstruction}

✅ *Status Penanganan:*
• Kode Insiden: *{$alertCode}*
• Tiket Prioritas: *{$ticketCode}* (*URGENT - Level 3*)
• Tim Teknisi Lapangan & Petugas Siaga 24/7 telah menerima *Alert Darurat* detik ini dan segera menghubungi / menuju lokasi Kakak.

_Percakapan ini telah dialihkan langsung ke Tim Tanggap Darurat. Mohon informasikan nomor Charger atau lokasi stasiun Kakak jika belum disebutkan._ 🙏⚡
TEXT;
    }
}

