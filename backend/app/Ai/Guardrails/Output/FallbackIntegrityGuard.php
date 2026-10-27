<?php

namespace App\Ai\Guardrails\Output;

use App\Ai\Guardrails\Contracts\OutputGuardrailInterface;
use App\Ai\Guardrails\Dto\GuardrailContext;
use App\Ai\Guardrails\Dto\GuardrailResult;
use Illuminate\Support\Facades\Log;

class FallbackIntegrityGuard implements OutputGuardrailInterface
{
    /**
     * Pola deteksi pesan error teknis API atau kegagalan jaringan yang berpotensi bocor ke pelanggan.
     */
    protected array $errorPatterns = [
        '/\b(?:rate\s+limit|quota\s+exceeded|maximum\s+context\s+length|upstream\s+connect\s+error)\b/i',
        '/\b(?:connection\s+(?:refused|reset|timed?\s*out)|curl\s+error\s+\d+|api\s+connection\s+error)\b/i',
        '/\b(?:internal\s+server\s+error|service\s+unavailable|bad\s+gateway|error\s+code\s*[:=]?\s*50[0-4])\b/i',
        '/\b(?:fatal\s+error|uncaught\s+exception|syntaxerror|typeerror)\b/i',
    ];

    /**
     * Inisialisasi guardrail dengan opsi konfigurasi template fallback.
     */
    public function __construct(
        protected bool $enabled = true,
        protected ?string $fallbackTemplate = null
    ) {
        $this->fallbackTemplate = $fallbackTemplate ?? 'Mohon maaf atas ketidaknyamanannya Kak {name}. Sistem kami sedang mengalami kendala teknis sesaat. Tim Customer Care kami siap membantu, atau Kakak dapat mengetik *Bantuan* untuk terhubung langsung dengan staf kami.';
    }

    /**
     * Nama unik guardrail output.
     */
    public function name(): string
    {
        return 'fallback_integrity_guard';
    }

    /**
     * Status apakah guardrail ini aktif.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Validasi integritas balasan output LLM dari kekosongan atau kebocoran pesan error teknis.
     */
    public function validateOutput(string $text, GuardrailContext $context): GuardrailResult
    {
        $trimmed = trim($text);

        // Cek apakah balasan kosong atau hanya berisi spasi
        if ($trimmed === '') {
            return $this->buildFallbackResult('empty_response', $context);
        }

        // Cek apakah balasan terlalu pendek / terpotong (misal hanya 1-2 karakter non-alfanumerik)
        if (mb_strlen($trimmed) < 3) {
            return $this->buildFallbackResult('truncated_response', $context);
        }

        // Cek apakah memuat pesan error teknis atau kegagalan koneksi API LLM
        foreach ($this->errorPatterns as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return $this->buildFallbackResult('technical_error_leak', $context);
            }
        }

        return GuardrailResult::allow($trimmed);
    }

    /**
     * Susun pesan fallback yang ramah dan terpersonalisasi.
     */
    protected function buildFallbackResult(string $triggerReason, GuardrailContext $context): GuardrailResult
    {
        $customerName = $context->getCustomerName() ?: 'Pelanggan';
        $fallbackMessage = str_replace('{name}', $customerName, $this->fallbackTemplate);

        Log::warning('[FallbackIntegrityGuard] AI output fallback triggered', [
            'reason' => $triggerReason,
            'conversation_id' => $context->getConversationId(),
            'customer_name' => $customerName,
        ]);

        return GuardrailResult::modify(
            modifiedText: $fallbackMessage,
            reason: 'fallback_integrity_triggered',
            telemetry: [
                'trigger' => $triggerReason,
            ]
        );
    }
}
