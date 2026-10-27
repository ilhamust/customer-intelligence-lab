<?php

namespace App\Ai\Guardrails\Input;

use App\Ai\Guardrails\Contracts\InputGuardrailInterface;
use App\Ai\Guardrails\Dto\GuardrailContext;
use App\Ai\Guardrails\Dto\GuardrailResult;

class LengthAndSpamGuard implements InputGuardrailInterface
{
    /**
     * Inisialisasi guardrail dengan ambang batas panjang dan status aktif.
     */
    public function __construct(
        protected int $minLength = 2,
        protected int $maxLength = 1000,
        protected bool $enabled = true
    ) {}

    /**
     * Nama unik guardrail.
     */
    public function name(): string
    {
        return 'length_and_spam_guard';
    }

    /**
     * Status apakah guardrail ini aktif.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Evaluasi batas panjang karakter dan deteksi spam karakter berulang.
     */
    public function validateInput(string $text, GuardrailContext $context): GuardrailResult
    {
        $trimmed = trim($text);
        $length = mb_strlen($trimmed);
        $customerName = $context->getCustomerName();

        // Validasi Batas Minimum Karakter
        if ($length < $this->minLength) {
            $fallback = "Halo Kak *{$customerName}*, pesan yang Kakak kirimkan terlalu singkat. Ada yang bisa kami bantu seputar stasiun pengisian daya kendaraan listrik Jarvis?";

            return GuardrailResult::block(
                fallbackResponse: $fallback,
                reason: 'input_too_short',
                action: 'reject',
                telemetry: [
                    'length' => $length,
                    'min_length' => $this->minLength,
                ]
            );
        }

        // Validasi Batas Maksimum Karakter
        if ($length > $this->maxLength) {
            $fallback = "Halo Kak *{$customerName}*, pesan yang Kakak kirimkan melebihi batas {$this->maxLength} karakter. Mohon sampaikan inti pertanyaan atau kendala Kakak secara lebih ringkas agar kami dapat segera membantu.";

            return GuardrailResult::block(
                fallbackResponse: $fallback,
                reason: 'input_too_long',
                action: 'reject',
                telemetry: [
                    'length' => $length,
                    'max_length' => $this->maxLength,
                ]
            );
        }

        // Deteksi Spam Karakter Berulang (misal: 'aaaaaaaaaaaaa...', '?????????????', '!!!!!!!!!!!')
        // Mendeteksi 12 atau lebih karakter tunggal yang sama persis berturut-turut tanpa spasi
        if (preg_match('/(.)\1{11,}/u', $trimmed)) {
            $fallback = "Halo Kak *{$customerName}*, kami mendeteksi karakter berulang pada pesan Kakak. Mohon kirimkan pertanyaan atau kendala Kakak dengan jelas ya.";

            return GuardrailResult::block(
                fallbackResponse: $fallback,
                reason: 'repetitive_character_spam',
                action: 'reject',
                telemetry: [
                    'length' => $length,
                    'pattern' => 'single_char_repetition_ge_12',
                ]
            );
        }

        // Deteksi Spam Frasa/Kata Berulang (misal: 'tes tes tes tes tes tes tes tes tes tes...')
        // Mendeteksi 8 kali atau lebih pengulangan kata yang sama
        if (preg_match('/\b(\w+)\b(?:\s+\1\b){7,}/ui', $trimmed)) {
            $fallback = "Halo Kak *{$customerName}*, mohon sampaikan pertanyaan atau kendala pengisian daya Kakak secara wajar ya. Ada yang bisa kami bantu?";

            return GuardrailResult::block(
                fallbackResponse: $fallback,
                reason: 'repetitive_word_spam',
                action: 'reject',
                telemetry: [
                    'length' => $length,
                    'pattern' => 'word_repetition_ge_8',
                ]
            );
        }

        // Jika lolos seluruh pemeriksaan panjang dan spam
        return GuardrailResult::allow($trimmed, [
            'length' => $length,
        ]);
    }
}
