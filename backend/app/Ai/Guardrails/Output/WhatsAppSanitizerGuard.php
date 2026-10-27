<?php

namespace App\Ai\Guardrails\Output;

use App\Ai\Guardrails\Contracts\OutputGuardrailInterface;
use App\Ai\Guardrails\Dto\GuardrailContext;
use App\Ai\Guardrails\Dto\GuardrailResult;

class WhatsAppSanitizerGuard implements OutputGuardrailInterface
{
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
        return 'whatsapp_sanitizer_guard';
    }

    /**
     * Status apakah guardrail ini aktif.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Evaluasi dan sanitasi teks balasan agar sesuai dengan standar format WhatsApp.
     */
    public function validateOutput(string $text, GuardrailContext $context): GuardrailResult
    {
        $sanitized = static::sanitize($text);

        if ($sanitized !== $text) {
            return GuardrailResult::modify(
                modifiedText: $sanitized,
                reason: 'whatsapp_formatting_applied'
            );
        }

        return GuardrailResult::allow($sanitized);
    }

    /**
     * Sanitasi dan konversi Markdown umum ke format teks WhatsApp.
     */
    public static function sanitize(string $text): string
    {
        // Konversi tabel Markdown menjadi butir ringkas WhatsApp (bullet points)
        $text = static::convertMarkdownTables($text);

        // Ubah Markdown Header (### Judul, ## Judul, # Judul) menjadi bold WhatsApp (*Judul*)
        $text = preg_replace('/^#{1,6}\s+(.+)$/m', '*$1*', $text);

        // bah Markdown Link [Label](url) menjadi Label (url)
        $text = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/u', '$1 ($2)', $text);

        // Ubah triple asterisks ***teks*** (bold + italic) menjadi *_teks_*
        $text = preg_replace('/\*{3}(.+?)\*{3}/s', '*_$1_*', $text);

        // Ubah double asterisks **teks** (bold) menjadi *teks*
        $text = preg_replace('/\*{2}(.+?)\*{2}/s', '*$1*', $text);

        // Ubah strikethrough ~~teks~~ menjadi ~teks~
        $text = preg_replace('/~~(.+?)~~/s', '~$1~', $text);

        // Bersihkan baris kosong berlebih (maksimal 2 baris baru berurutan)
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /**
     * Konversi struktur tabel Markdown menjadi daftar poin (bullet points) yang ramah WhatsApp.
     */
    protected static function convertMarkdownTables(string $text): string
    {
        // Deteksi blok tabel Markdown yang memiliki baris header, separator (|---|), dan baris data
        $pattern = '/(?:^[ \t]*\|.+?\|[ \t]*(?:\r?\n|$))+/m';

        return preg_replace_callback($pattern, function ($matches) {
            $tableBlock = trim($matches[0]);
            $lines = preg_split('/\r?\n/', $tableBlock);

            if (count($lines) < 2) {
                return $tableBlock;
            }

            // Ekstrak header (baris pertama)
            $rawHeaderLine = array_shift($lines);
            $headers = array_map('trim', explode('|', trim($rawHeaderLine, " \t|")));

            // Baris kedua biasanya pemisah (misal |---|---|)
            if (!empty($lines)) {
                $separatorCandidate = trim($lines[0]);
                if (preg_match('/^[\s|:\-]+$/', $separatorCandidate)) {
                    array_shift($lines);
                }
            }

            // Jika tidak ada baris data tersisa, kembalikan teks asli
            if (empty($lines)) {
                return $tableBlock;
            }

            $bulletLines = [];
            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                if (empty($trimmedLine) || !str_contains($trimmedLine, '|')) {
                    continue;
                }

                $cells = array_map('trim', explode('|', trim($trimmedLine, " \t|")));

                if (count($cells) === 2 && count($headers) >= 2) {
                    // Kasus 2 kolom: • *Header1/Cell1*: Cell2
                    $bulletLines[] = "• *{$cells[0]}*: {$cells[1]}";
                } else {
                    // Kasus multi kolom: gabungkan per header
                    $rowParts = [];
                    foreach ($cells as $idx => $cell) {
                        if ($cell === '') {
                            continue;
                        }
                        $colName = $headers[$idx] ?? '';
                        if (!empty($colName)) {
                            $rowParts[] = "{$colName}: {$cell}";
                        } else {
                            $rowParts[] = $cell;
                        }
                    }

                    if (!empty($rowParts)) {
                        $bulletLines[] = "• " . implode(' | ', $rowParts);
                    }
                }
            }

            return !empty($bulletLines) ? implode("\n", $bulletLines) : $tableBlock;
        }, $text);
    }
}
