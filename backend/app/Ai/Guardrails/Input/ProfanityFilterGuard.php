<?php

namespace App\Ai\Guardrails\Input;

use App\Ai\Guardrails\Contracts\InputGuardrailInterface;
use App\Ai\Guardrails\Dto\GuardrailContext;
use App\Ai\Guardrails\Dto\GuardrailResult;
use App\Service\Bot\Contracts\TicketService;
use Illuminate\Support\Facades\Log;

class ProfanityFilterGuard implements InputGuardrailInterface
{
    /**
     * Pola regex kata kasar / makian berat (Bahasa Indonesia & Bahasa Inggris).
     */
    protected array $profanityWords = [
        // Kata kasar / makian Bahasa Indonesia & daerah
        'anjing', 'anjir', 'anjay', 'babi', 'bangsat', 'bajingan', 'brengsek',
        'kontol', 'memek', 'jembut', 'ngentot', 'pantek', 'perek', 'peler',
        'tolol', 'bego', 'goblok', 'idiot', 'tai', 'taek', 'jancok', 'dancok',
        'cok', 'asu', 'kampret', 'setan', 'keparat',

        // Kata kasar Bahasa Inggris
        'fuck', 'fucking', 'fucker', 'shit', 'asshole', 'bitch', 'bastard',
        'dick', 'pussy', 'cunt', 'bullshit', 'wtf',
    ];

    /**
     * Inisialisasi guardrail dengan opsi status aktif dan custom words tambahan.
     */
    public function __construct(
        protected bool $enabled = true,
        array $customWords = []
    ) {
        if (! empty($customWords)) {
            $this->profanityWords = array_unique(array_merge($this->profanityWords, $customWords));
        }
    }

    /**
     * Nama unik guardrail.
     */
    public function name(): string
    {
        return 'profanity_filter_guard';
    }

    /**
     * Status apakah guardrail ini aktif.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Evaluasi apakah pesan pelanggan mengandung kata kasar / makian berat.
     * Jika ya, berikan respon santun berempati dan eskalasikan ke antrean tiket normal (service_complaint).
     */
    public function validateInput(string $text, GuardrailContext $context): GuardrailResult
    {
        $trimmed = trim($text);

        // Buat regex word boundary case-insensitive
        $escapedWords = array_map(fn ($w) => preg_quote($w, '/'), $this->profanityWords);
        $pattern = '/\b(' . implode('|', $escapedWords) . ')\b/ui';

        if (preg_match($pattern, $trimmed, $matches)) {
            $matchedWord = $matches[0] ?? '';
            $customerName = $context->getCustomerName();

            Log::warning('[ProfanityFilterGuard] Profanity detected in incoming customer message', [
                'matched_word' => $matchedWord,
                'conversation_id' => $context->getConversationId(),
                'customer_name' => $customerName,
            ]);

            // Jika percakapan tersimpan di DB, eskalasikan ke tiket antrean normal (service_complaint)
            if ($context->conversation && $context->conversation->id) {
                /** @var TicketService $ticketService */
                $ticketService = app(TicketService::class);

                // Buat tiket antrean normal tanpa prioritas khusus (fair FIFO)
                $ticket = $ticketService->createTicket(
                    conversation: $context->conversation,
                    category: TicketService::CATEGORY_SERVICE_COMPLAINT,
                    notes: 'Eskalasi otomatis: Komplain pelayanan dengan indikasi emosi/kata kasar pelanggan',
                    metadata: [
                        'issue_summary' => $trimmed,
                        'escalated_by' => 'profanity_filter_guard',
                        'matched_word' => $matchedWord,
                    ]
                );

                $queuePosition = $ticketService->calculateQueuePosition($ticket);

                $response = "Halo Kak *{$customerName}*, kami memahami kendala yang Kakak alami dan mohon maaf sebesar-besarnya atas ketidaknyamanan ini. Kami mohon agar komunikasi tetap disampaikan dengan bahasa yang santun ya Kak 🙏\n\nPengaduan Kakak mengenai *Komplain Pelayanan* telah kami teruskan ke Customer Service. Saat ini Kakak berada dalam antrean nomor *#{$queuePosition}*. Tim Customer Care kami akan segera melayani Kakak.";

                return GuardrailResult::bypass(
                    bypassResponse: $response,
                    reason: 'profanity_escalated_to_service_complaint',
                    action: 'escalate_to_human',
                    telemetry: [
                        'matched_word' => $matchedWord,
                        'ticket_id' => $ticket->id,
                        'ticket_code' => $ticket->ticket_code,
                        'queue_position' => $queuePosition,
                        'category' => $ticket->category,
                    ]
                );
            }

            // Fallback respon santun dengan batasan profesional (tanpa DB persisten)
            $fallback = "Halo Kak *{$customerName}*, kami memahami kendala yang Kakak alami dan mohon maaf atas ketidaknyamanan yang terjadi. Kami mohon agar komunikasi tetap disampaikan secara santun dan saling menghargai ya Kak. Ada yang bisa kami bantu seputar pengisian daya kendaraan listrik Jarvis?";

            return GuardrailResult::block(
                fallbackResponse: $fallback,
                reason: 'profanity_detected',
                action: 'reject',
                telemetry: [
                    'matched_word' => $matchedWord,
                ]
            );
        }

        // Lolos tanpa terdeteksi kata kasar
        return GuardrailResult::allow($trimmed);
    }
}
