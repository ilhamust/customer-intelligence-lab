<?php

namespace App\Ai\Guardrails\Output;

use App\Ai\Guardrails\Contracts\OutputGuardrailInterface;
use App\Ai\Guardrails\Dto\GuardrailContext;
use App\Ai\Guardrails\Dto\GuardrailResult;
use Illuminate\Support\Facades\Log;

class SystemLeakAndIdentityGuard implements OutputGuardrailInterface
{
    /**
     * Pola regex identitas model AI pihak ketiga yang perlu diganti ke persona resmi Jarvis.
     */
    protected array $identityPatterns = [
        '/(?:saya|aku)\s+(?:adalah\s+)?(?:sebuah\s+)?(?:model\s+(?:bahasa\s+besar|ai|kecerdasan\s+buatan)|large\s+language\s+model|llm|kecerdasan\s+buatan|ai)\s+(?:yang\s+dilatih\s+oleh|dikembangkan\s+oleh|buatan|dari)?\s*(?:openai|google|anthropic|meta|gemini|chatgpt)/ui' => 'saya adalah asisten Customer Care Jarvis',
        '/(?:sebagai\s+)?(?:model\s+(?:bahasa\s+besar|ai|kecerdasan\s+buatan)|large\s+language\s+model|llm|kecerdasan\s+buatan|ai)\s+(?:yang\s+dilatih\s+oleh|dikembangkan\s+oleh|buatan|dari)\s*(?:openai|google|anthropic|meta|gemini|chatgpt)/ui' => 'sebagai asisten Customer Care Jarvis',
        '/(?:as\s+an\s+ai\s+language\s+model|as\s+a\s+large\s+language\s+model)\s*(?:trained\s+by|developed\s+by\s+(?:openai|google|anthropic|meta))?/ui' => 'as Jarvis Customer Care assistant',
        '/(?:saya|aku)\s+(?:adalah\s+)?(?:chatgpt|gpt-4o?|gemini(?:\s+flash|\s+pro)?|claude|llama)/ui' => 'saya adalah asisten Customer Care Jarvis',
        '/(?:i\s+am\s+(?:an?\s+)?(?:ai|large\s+language\s+model)\s+(?:created|developed|trained)\s+by\s+(?:google|openai|anthropic|meta))/ui' => 'I am Jarvis Customer Care assistant',
    ];

    /**
     * Pola regex potongan teks prompt internal yang tidak boleh bocor keluar.
     */
    protected array $promptLeakPatterns = [
        '/===+\s*(?:PROFIL\s+PELANGGAN\s+AKTIF|CUSTOMER\s+360°|PROTOKOL\s+ESKALASI)\s*===+/ui',
        '/\b(?:STRICT\s+DOMAIN\s+GUARDRAILS|PANDUAN\s+KOMUNIKASI\s*&\s*INTERAKSI|INSTRUKSI\s+EKSEKUSI\s+ESKALASI)\b/ui',
        '/\b(?:JarvisKnowledgeBase|CreateSupportTicketTool|GenerateAiReplyJob)\b/ui',
    ];

    /**
     * Pola regex API key atau credential rahasia.
     */
    protected array $secretPatterns = [
        '/\b(?:sk-[a-zA-Z0-9_\-]{20,})\b/u', // OpenAI API Key
        '/\b(?:AIza[0-9A-Za-z\-_]{35})\b/u',  // Google/Gemini API Key
        '/\b(?:gsk_[a-zA-Z0-9_\-]{20,})\b/u', // Groq API Key
        '/(?:api[_-]?key|secret[_-]?key)\s*[:=]\s*["\']?[a-zA-Z0-9_\-\.]{16,}["\']?/ui',
    ];

    /**
     * Inisialisasi guardrail dengan opsi status aktif.
     */
    public function __construct(
        protected bool $enabled = true
    ) {
    }

    /**
     * Nama unik guardrail output.
     */
    public function name(): string
    {
        return 'system_leak_and_identity_guard';
    }

    /**
     * Status apakah guardrail ini aktif.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Evaluasi dan sanitasi teks balasan untuk menyaring identitas model AI dan kebocoran sistem.
     */
    public function validateOutput(string $text, GuardrailContext $context): GuardrailResult
    {
        $currentText = trim($text);
        $wasModified = false;
        $mitigatedIssues = [];

        // Sanitasi Identitas Model Pihak Ketiga -> Persona Resmi Jarvis
        foreach ($this->identityPatterns as $pattern => $replacement) {
            if (preg_match($pattern, $currentText)) {
                $currentText = preg_replace($pattern, $replacement, $currentText);
                $wasModified = true;
                $mitigatedIssues[] = 'third_party_identity_replaced';
            }
        }

        // Sanitasi Kebocoran Potongan System Prompt Internal
        foreach ($this->promptLeakPatterns as $pattern) {
            if (preg_match($pattern, $currentText)) {
                $currentText = preg_replace($pattern, '', $currentText);
                $wasModified = true;
                $mitigatedIssues[] = 'system_prompt_leak_redacted';
            }
        }

        // Sanitasi Kebocoran API Key / Token Kredensial Rahasia
        foreach ($this->secretPatterns as $pattern) {
            if (preg_match($pattern, $currentText)) {
                $currentText = preg_replace($pattern, '[REDACTED_SECRET]', $currentText);
                $wasModified = true;
                $mitigatedIssues[] = 'api_key_leak_redacted';
            }
        }

        // Bersihkan whitespace berlebih setelah redaction
        $currentText = preg_replace("/\n{3,}/", "\n\n", trim($currentText));

        if ($wasModified) {
            $mitigatedIssues = array_unique($mitigatedIssues);

            Log::warning('[SystemLeakAndIdentityGuard] System leak or third-party identity mitigated in AI response', [
                'conversation_id' => $context->getConversationId(),
                'customer_name' => $context->getCustomerName(),
                'issues' => $mitigatedIssues,
            ]);

            return GuardrailResult::modify(
                modifiedText: $currentText,
                reason: 'system_leak_or_identity_mitigated',
                telemetry: [
                    'issues' => $mitigatedIssues,
                ]
            );
        }

        return GuardrailResult::allow($currentText);
    }
}
