<?php

namespace App\Ai\Guardrails\Input;

use App\Ai\Guardrails\Contracts\InputGuardrailInterface;
use App\Ai\Guardrails\Dto\GuardrailContext;
use App\Ai\Guardrails\Dto\GuardrailResult;

class PromptInjectionGuard implements InputGuardrailInterface
{
    /**
     * Kumpulan pola regex untuk mendeteksi upaya manipulasi prompt / jailbreak.
     */
    protected array $patterns = [
        // Instruction Override (Inggris & Indonesia)
        'instruction_override' => [
            '/(?:ignore|forget|disregard|override)\s+(?:all\s+)?(?:previous|prior|above|system)\s+(?:instructions|prompts?|commands?|rules?)/i',
            '/(?:abaikan|lupakan|hapus|batalkan)\s+(?:semua\s+)?(?:instruksi|perintah|aturan|prompt)\s+(?:sebelumnya|awal|sistem)/i',
            '/(?:stop\s+being|kamu\s+bukan\s+lagi)\s+(?:a\s+)?(?:customer\s+care|asisten|jarvis)/i',
        ],

        // Persona Hijacking & Jailbreak (DAN, Developer Mode, Roleplay Tanpa Batas)
        'persona_hijacking' => [
            '/(?:you\s+are\s+now|act\s+as|pretend\s+to\s+be|play\s+the\s+role\s+of)\s+(?:dan|developer\s+mode|an\s+unfiltered|an\s+unrestricted|a\s+hacker|jailbreak)/i',
            '/(?:kamu\s+sekarang\s+adalah|berperanlah\s+sebagai|jadilah)\s+(?:dan|hacker|bot\s+bebas|tanpa\s+aturan|mode\s+pengembang|ai\s+tanpa\s+filter)/i',
            '/\b(?:jailbreak|dan\s+mode|developer\s+mode|unfiltered\s+mode|bypass\s+rules?)\b/i',
        ],

        // System Prompt & Secret Leak Probing
        'system_prompt_leak' => [
            '/(?:reveal|show|display|print|output|repeat|tell\s+me)\s+(?:your|the)?\s*(?:system\s+prompts?|system\s+instructions?|internal\s+instructions?|secret\s+keys?|api\s*keys?)/i',
            '/(?:tampilkan|tuliskan|bocorkan|sebutkan)\s+(?:isi\s+)?(?:system\s+prompts?|instruksi\s+sistem|prompt\s+rahasia|prompt\s+internal|kunci\s+rahasia|api\s*keys?)/i',
            '/\b(?:what\s+are\s+your\s+(?:exact\s+)?(?:instructions|rules|system\s+prompts?))\b/i',
        ],

        // Tag / Delimiter Injection Attack
        'tag_injection' => [
            '/<\/?(?:system|instruction|admin|override)>/i',
            '/\[(?:system|instruction|admin)\]/i',
        ],
    ];

    /**
     * Inisialisasi guardrail dengan opsi status aktif dan pola kustom tambahan.
     */
    public function __construct(
        protected bool $enabled = true,
        array $customPatterns = []
    ) {
        if (! empty($customPatterns)) {
            $this->patterns = array_merge_recursive($this->patterns, $customPatterns);
        }
    }

    /**
     * Nama unik guardrail.
     */
    public function name(): string
    {
        return 'prompt_injection_guard';
    }

    /**
     * Status apakah guardrail ini aktif.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Evaluasi teks input untuk mendeteksi upaya jailbreak atau injeksi prompt.
     */
    public function validateInput(string $text, GuardrailContext $context): GuardrailResult
    {
        $trimmed = trim($text);
        $customerName = $context->getCustomerName();

        foreach ($this->patterns as $category => $regexList) {
            foreach ($regexList as $pattern) {
                if (preg_match($pattern, $trimmed, $matches)) {
                    $matchedSnippet = $matches[0] ?? '';

                    $fallback = "Halo Kak *{$customerName}*, saya adalah asisten Customer Care Jarvis yang bertugas membantu informasi dan kendala seputar pengisian daya kendaraan listrik. Ada yang bisa kami bantu terkait stasiun pengisian daya Jarvis?";

                    return GuardrailResult::block(
                        fallbackResponse: $fallback,
                        reason: 'prompt_injection_detected',
                        action: 'reject',
                        telemetry: [
                            'category' => $category,
                            'matched_pattern' => $pattern,
                            'matched_snippet' => $matchedSnippet,
                        ]
                    );
                }
            }
        }

        // Lolos seluruh pemeriksaan injeksi prompt
        return GuardrailResult::allow($trimmed);
    }
}
