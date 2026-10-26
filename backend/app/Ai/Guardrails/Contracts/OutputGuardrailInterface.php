<?php

namespace App\Ai\Guardrails\Contracts;

use App\Ai\Guardrails\Dto\GuardrailContext;
use App\Ai\Guardrails\Dto\GuardrailResult;

interface OutputGuardrailInterface
{
    /**
     * Nama unik pengenal guardrail output (misal: 'financial_liability_guard').
     */
    public function name(): string;

    /**
     * Apakah guardrail ini sedang aktif digunakan.
     */
    public function isEnabled(): bool;

    /**
     * Evaluasi dan sanitasi teks balasan yang dihasilkan LLM sebelum dikirim ke pelanggan.
     *
     * @param  string  $text  Teks balasan dari LLM
     * @param  GuardrailContext  $context  Konteks percakapan dan pelanggan
     * @return GuardrailResult  Hasil evaluasi (allowed, modified, atau blocked/fallback)
     */
    public function validateOutput(string $text, GuardrailContext $context): GuardrailResult;
}
