<?php

namespace App\Ai\Guardrails\Input;

use App\Ai\Guardrails\Contracts\InputGuardrailInterface;
use App\Ai\Guardrails\Dto\GuardrailContext;
use App\Ai\Guardrails\Dto\GuardrailResult;
use App\Service\Bot\Contracts\TicketService;
use Illuminate\Support\Facades\Log;

class DirectHumanBypassGuard implements InputGuardrailInterface
{
    /**
     * Kumpulan pola regex untuk mendeteksi permintaan eksplisit terhubung ke CS manusia.
     */
    protected array $patterns = [
        // 1. Pesan panggilan singkat mandiri (standalone call)
        '/^(?:halo\s+|hi\s+|hai\s+)?(?:cs|admin|operator|customer\s+service|customer\s+care)[!?. ]*$/ui',

        // 2. Permintaan bicara / terhubung ke CS atau manusia
        '/(?:bicara|ngobrol|hubung(?:i|kan)|sambung(?:kan)?|alihkan)\s+(?:ke\s+|dengan\s+|sama\s+)?(?:cs|customer\s+service|customer\s+care|admin|manusia|orang\s+asli|operator|staf)/ui',

        // 3. Permintaan bantuan khusus orang asli / manusia
        '/(?:butuh|minta|tolong)\s+(?:bantuan\s+)?(?:manusia|orang\s+asli|operator|staf\s+resmi)/ui',

        // 4. Keinginan eksplisit berkomunikasi dengan staf manusia
        '/(?:mau|ingin)\s+(?:bicara|ngobrol|komunikasi|konsultasi)\s+(?:dengan|sama)\s+(?:manusia|orang\s+asli|admin|cs|operator)/ui',

        // 5. Penolakan berkomunikasi dengan bot
        '/(?:jangan\s+bot|gamau\s+bot|ga\s+mau\s+bot|bukan\s+bot|stop\s+bot|saya\s+mau\s+(?:manusia|orang\s+asli))/ui',

        // 6. Pola Bahasa Inggris (speak to human / connect to operator)
        '/(?:speak|talk)\s+(?:to|with)\s+(?:a\s+)?(?:human|agent|operator|person|representative)/ui',
        '/(?:connect|transfer)\s+(?:me\s+)?to\s+(?:a\s+)?(?:human|agent|operator|representative)/ui',
    ];

    /**
     * Inisialisasi guardrail dengan opsi status aktif dan custom patterns tambahan.
     */
    public function __construct(
        protected bool $enabled = true,
        array $customPatterns = []
    ) {
        if (! empty($customPatterns)) {
            $this->patterns = array_merge($this->patterns, $customPatterns);
        }
    }

    /**
     * Nama unik guardrail.
     */
    public function name(): string
    {
        return 'direct_human_bypass_guard';
    }

    /**
     * Status apakah guardrail ini aktif.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Evaluasi apakah pesan pelanggan secara tegas meminta berbicara dengan CS manusia.
     * Jika ya, langsung buat tiket antrean standar FIFO tanpa memanggil LLM (Fast Intent Bypass).
     */
    public function validateInput(string $text, GuardrailContext $context): GuardrailResult
    {
        $trimmed = trim($text);

        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $trimmed, $matches)) {
                $matchedPattern = $matches[0] ?? $pattern;
                $customerName = $context->getCustomerName();

                Log::info('[DirectHumanBypassGuard] Explicit human CS request detected, bypassing LLM', [
                    'matched' => $matchedPattern,
                    'conversation_id' => $context->getConversationId(),
                    'customer_name' => $customerName,
                ]);

                // Jika percakapan valid dan tersimpan di database, langsung buat tiket antrean via TicketService
                if ($context->conversation && $context->conversation->id) {
                    /** @var TicketService $ticketService */
                    $ticketService = app(TicketService::class);

                    $ticket = $ticketService->createTicket(
                        conversation: $context->conversation,
                        category: 'general_human',
                        metadata: [
                            'issue_summary' => $trimmed,
                            'escalated_by' => 'direct_human_bypass_guard',
                            'matched_intent' => $matchedPattern,
                        ]
                    );

                    $queuePosition = $ticketService->calculateQueuePosition($ticket);
                    $officialQueueText = $ticketService->buildQueueNotificationText(
                        $queuePosition,
                        $ticket->category,
                        $customerName
                    );

                    return GuardrailResult::bypass(
                        bypassResponse: $officialQueueText,
                        reason: 'direct_human_bypass',
                        action: 'escalate_to_human',
                        telemetry: [
                            'ticket_id' => $ticket->id,
                            'ticket_code' => $ticket->ticket_code,
                            'queue_position' => $queuePosition,
                            'category' => $ticket->category,
                            'matched_pattern' => $matchedPattern,
                        ]
                    );
                }

                // Fallback untuk unit testing atau saat objek conversation belum terikat
                $fallbackBypass = "Halo Kak *{$customerName}*, permintaan Kakak untuk terhubung dengan Customer Service telah kami terima. Mohon tunggu sebentar ya, tim Customer Care kami akan segera melayani Kakak.";

                return GuardrailResult::bypass(
                    bypassResponse: $fallbackBypass,
                    reason: 'direct_human_bypass',
                    action: 'escalate_to_human',
                    telemetry: [
                        'matched_pattern' => $matchedPattern,
                    ]
                );
            }
        }

        // Lolos tanpa bypass (bukan permintaan langsung CS, teruskan ke pipeline / LLM)
        return GuardrailResult::allow($trimmed);
    }
}
