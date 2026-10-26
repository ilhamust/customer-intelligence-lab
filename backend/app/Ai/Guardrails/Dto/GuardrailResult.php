<?php

namespace App\Ai\Guardrails\Dto;

class GuardrailResult
{
    public const STATUS_ALLOWED = 'allowed';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_BYPASSED = 'bypassed';

    public function __construct(
        protected string $status,
        protected string $text,
        protected ?string $reason = null,
        protected ?string $action = null,
        protected array $telemetry = []
    ) {}

    /**
     * Factory untuk hasil pemeriksaan yang lolos tanpa intervensi.
     */
    public static function allow(string $text, array $telemetry = []): self
    {
        return new self(
            status: self::STATUS_ALLOWED,
            text: $text,
            reason: null,
            action: 'allow',
            telemetry: $telemetry
        );
    }

    /**
     * Factory untuk hasil pemeriksaan yang dimodifikasi/disanitasi tapi tetap diizinkan.
     */
    public static function modify(string $modifiedText, string $reason, array $telemetry = []): self
    {
        return new self(
            status: self::STATUS_ALLOWED,
            text: $modifiedText,
            reason: $reason,
            action: 'modify',
            telemetry: $telemetry
        );
    }

    /**
     * Factory untuk hasil pemeriksaan yang melanggar aturan dan diblokir (early exit dengan safe response).
     */
    public static function block(
        string $fallbackResponse,
        string $reason,
        ?string $action = 'reject',
        array $telemetry = []
    ): self {
        return new self(
            status: self::STATUS_BLOCKED,
            text: $fallbackResponse,
            reason: $reason,
            action: $action,
            telemetry: $telemetry
        );
    }

    /**
     * Factory untuk hasil pemeriksaan yang dialihkan langsung (fast intent bypass tanpa panggil LLM).
     */
    public static function bypass(
        string $bypassResponse,
        string $reason,
        ?string $action = 'escalate_to_human',
        array $telemetry = []
    ): self {
        return new self(
            status: self::STATUS_BYPASSED,
            text: $bypassResponse,
            reason: $reason,
            action: $action,
            telemetry: $telemetry
        );
    }

    /**
     * Apakah teks diizinkan untuk diteruskan (status allowed).
     */
    public function isAllowed(): bool
    {
        return $this->status === self::STATUS_ALLOWED;
    }

    /**
     * Apakah teks diblokir karena melanggar aturan keamanan (status blocked).
     */
    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    /**
     * Apakah alur dialihkan langsung tanpa memanggil LLM (status bypassed).
     */
    public function isBypassed(): bool
    {
        return $this->status === self::STATUS_BYPASSED;
    }

    /**
     * Dapatkan teks hasil modifikasi/sanitasi atau teks respon alternatif.
     */
    public function getModifiedText(): string
    {
        return $this->text;
    }

    /**
     * Dapatkan status string ('allowed', 'blocked', 'bypassed').
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Dapatkan aksi yang diambil ('allow', 'modify', 'reject', 'escalate_to_human', dll).
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * Dapatkan alasan intervensi / nama rule yang terpicu.
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * Dapatkan data telemetri (waktu eksekusi, pola regex, metadata rule).
     */
    public function getTelemetry(): array
    {
        return $this->telemetry;
    }
}
