<?php

namespace App\Ai\Guardrails;

use App\Ai\Guardrails\Contracts\InputGuardrailInterface;
use App\Ai\Guardrails\Contracts\OutputGuardrailInterface;
use App\Ai\Guardrails\Dto\GuardrailContext;
use App\Ai\Guardrails\Dto\GuardrailResult;
use Illuminate\Support\Facades\Log;

class GuardrailManager
{
    /**
     * Daftar guardrail input yang terdaftar.
     *
     * @var InputGuardrailInterface[]
     */
    protected array $inputGuardrails = [];

    /**
     * Daftar guardrail output yang terdaftar.
     *
     * @var OutputGuardrailInterface[]
     */
    protected array $outputGuardrails = [];

    /**
     * Inisialisasi manager dengan kumpulan guardrail awal.
     *
     * @param  InputGuardrailInterface[]  $inputGuardrails
     * @param  OutputGuardrailInterface[]  $outputGuardrails
     */
    public function __construct(array $inputGuardrails = [], array $outputGuardrails = [])
    {
        foreach ($inputGuardrails as $guard) {
            $this->addInputGuardrail($guard);
        }

        foreach ($outputGuardrails as $guard) {
            $this->addOutputGuardrail($guard);
        }
    }

    /**
     * Daftarkan guardrail input ke pipeline.
     */
    public function addInputGuardrail(InputGuardrailInterface $guardrail): self
    {
        $this->inputGuardrails[] = $guardrail;

        return $this;
    }

    /**
     * Daftarkan guardrail output ke pipeline.
     */
    public function addOutputGuardrail(OutputGuardrailInterface $guardrail): self
    {
        $this->outputGuardrails[] = $guardrail;

        return $this;
    }

    /**
     * Dapatkan seluruh guardrail input yang terdaftar.
     *
     * @return InputGuardrailInterface[]
     */
    public function getInputGuardrails(): array
    {
        return $this->inputGuardrails;
    }

    /**
     * Dapatkan seluruh guardrail output yang terdaftar.
     *
     * @return OutputGuardrailInterface[]
     */
    public function getOutputGuardrails(): array
    {
        return $this->outputGuardrails;
    }

    /**
     * Eksekusi pipeline guardrail input secara sekuensial (sebelum LLM dipanggil).
     * Jika terjadi pelanggaran (blocked) atau fast intent (bypassed), pipeline berhenti (early exit).
     */
    public function validateInput(string $text, GuardrailContext $context): GuardrailResult
    {
        $currentText = $text;
        $telemetries = [];

        foreach ($this->inputGuardrails as $guardrail) {
            if (! $guardrail->isEnabled()) {
                continue;
            }

            $startTime = microtime(true);
            $result = $guardrail->validateInput($currentText, $context);
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            $telemetries[$guardrail->name()] = [
                'status' => $result->getStatus(),
                'elapsed_ms' => $elapsedMs,
                'reason' => $result->getReason(),
                'action' => $result->getAction(),
                ...$result->getTelemetry(),
            ];

            // Early exit jika pesan diblokir (pelanggaran keamanan / spam)
            if ($result->isBlocked()) {
                Log::warning('[GuardrailManager::validateInput] Input blocked by guardrail', [
                    'guardrail' => $guardrail->name(),
                    'reason' => $result->getReason(),
                    'original_text' => $text,
                    'action' => $result->getAction(),
                    'conversation_id' => $context->getConversationId(),
                    'customer_phone' => $context->getCustomerPhone(),
                    'elapsed_ms' => $elapsedMs,
                ]);

                return new GuardrailResult(
                    status: GuardrailResult::STATUS_BLOCKED,
                    text: $result->getModifiedText(),
                    reason: $result->getReason(),
                    action: $result->getAction(),
                    telemetry: $telemetries
                );
            }

            // Early exit jika pesan dialihkan langsung (fast bypass ke antrean CS manusia)
            if ($result->isBypassed()) {
                Log::warning('[GuardrailManager::validateInput] Input bypassed to human by guardrail', [
                    'guardrail' => $guardrail->name(),
                    'reason' => $result->getReason(),
                    'original_text' => $text,
                    'action' => $result->getAction(),
                    'conversation_id' => $context->getConversationId(),
                    'elapsed_ms' => $elapsedMs,
                ]);

                return new GuardrailResult(
                    status: GuardrailResult::STATUS_BYPASSED,
                    text: $result->getModifiedText(),
                    reason: $result->getReason(),
                    action: $result->getAction(),
                    telemetry: $telemetries
                );
            }

            // Teruskan teks yang mungkin telah dimodifikasi ke guardrail berikutnya
            $currentText = $result->getModifiedText();
        }

        return GuardrailResult::allow($currentText, $telemetries);
    }

    /**
     * Eksekusi pipeline guardrail output secara sekuensial (setelah LLM mengembalikan respon).
     * Menyaring komitmen finansial sepihak, kebocoran sistem, dan mensanitasi format WhatsApp.
     */
    public function validateOutput(string $text, GuardrailContext $context): GuardrailResult
    {
        $currentText = $text;
        $telemetries = [];
        $wasModified = false;
        $lastReason = null;

        foreach ($this->outputGuardrails as $guardrail) {
            if (! $guardrail->isEnabled()) {
                continue;
            }

            $startTime = microtime(true);
            $result = $guardrail->validateOutput($currentText, $context);
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            $telemetries[$guardrail->name()] = [
                'status' => $result->getStatus(),
                'elapsed_ms' => $elapsedMs,
                'reason' => $result->getReason(),
                'action' => $result->getAction(),
                ...$result->getTelemetry(),
            ];

            // Early exit jika output diblokir secara total dan diganti ke fallback resmi
            if ($result->isBlocked()) {
                Log::warning('[GuardrailManager::validateOutput] Output blocked and replaced by fallback', [
                    'guardrail' => $guardrail->name(),
                    'reason' => $result->getReason(),
                    'original_text' => $text,
                    'action' => $result->getAction() ?? 'fallback',
                    'conversation_id' => $context->getConversationId(),
                    'elapsed_ms' => $elapsedMs,
                ]);

                return new GuardrailResult(
                    status: GuardrailResult::STATUS_BLOCKED,
                    text: $result->getModifiedText(),
                    reason: $result->getReason(),
                    action: $result->getAction() ?? 'fallback',
                    telemetry: $telemetries
                );
            }

            // Catat jika teks mengalami modifikasi/sanitasi
            if ($result->getModifiedText() !== $currentText || $result->getAction() === 'modify') {
                $wasModified = true;
                $lastReason = $result->getReason() ?? $lastReason;
                $currentText = $result->getModifiedText();
            }
        }

        if ($wasModified) {
            Log::warning('[GuardrailManager::validateOutput] Output modified by guardrail pipeline', [
                'reason' => $lastReason,
                'original_text' => $text,
                'action' => 'modify',
                'conversation_id' => $context->getConversationId(),
                'telemetries' => $telemetries,
            ]);

            return GuardrailResult::modify($currentText, $lastReason ?? 'sanitized', $telemetries);
        }

        return GuardrailResult::allow($currentText, $telemetries);
    }
}
