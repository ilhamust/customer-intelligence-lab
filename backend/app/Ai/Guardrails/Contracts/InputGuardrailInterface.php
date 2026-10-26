<?php

namespace App\Ai\Guardrails\Contracts;

use App\Ai\Guardrails\Dto\GuardrailContext;
use App\Ai\Guardrails\Dto\GuardrailResult;

interface InputGuardrailInterface
{
    /**
     * Nama unik pengenal guardrail input (misal: 'prompt_injection_guard').
     */
    public function name(): string;

    /**
     * Apakah guardrail ini sedang aktif digunakan.
     */
    public function isEnabled(): bool;

    /**
     * Evaluasi teks input dari pelanggan sebelum diteruskan ke LLM.
     *
     * @param  string  $text  Teks pesan dari pelanggan
     * @param  GuardrailContext  $context  Konteks percakapan dan pelanggan
     * @return GuardrailResult  Hasil evaluasi (allowed, blocked, atau bypassed)
     */
    public function validateInput(string $text, GuardrailContext $context): GuardrailResult;
}
