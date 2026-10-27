<?php

namespace App\Ai\Guardrails\Output;

use App\Ai\Guardrails\Contracts\OutputGuardrailInterface;
use App\Ai\Guardrails\Dto\GuardrailContext;
use App\Ai\Guardrails\Dto\GuardrailResult;
use Illuminate\Support\Facades\Log;

class FinancialLiabilityGuard implements OutputGuardrailInterface
{
    /**
     * Pola regex klaim finansial sepihak / janji transfer / penambahan saldo manual.
     */
    protected array $liabilityPatterns = [
        // Klaim sudah transfer / sudah refund
        'claim_refund_done' => [
            '/(?:sudah|telah)\s+(?:kami|saya|pihak\s+jarvis)\s+(?:transfer|kembalikan|refund|kirimkan\s+uang|ganti\s+rugi)/ui',
            '/(?:uang|dana|biaya)\s+(?:kakak|anda|pengisian)?\s*(?:sudah|telah)\s+(?:kami\s+)?(?:kembalikan|transfer|refund|kirim)/ui',
        ],

        // Janji ganti rugi / kompensasi nominal tertentu sepihak
        'promise_compensation' => [
            '/(?:akan\s+kami\s+ganti\s+rugi|akan\s+kami\s+transfer|kami\s+berikan\s+kompensasi\s+sebesar|akan\s+kami\s+kembalikan\s+sebesar)\s*(?:rp\.?|uang|dana)?\s*[\d\.,]+/ui',
            '/(?:kompensasi|ganti\s+rugi|refund)\s+(?:finansial|sebesar)\s*(?:rp\.?)\s*[\d\.,]+/ui',
            '/(?:kami\s+akan\s+mengirimkan\s+dana\s+sebesar)/ui',
        ],

        // Klaim penambahan saldo / poin sepihak
        'claim_balance_added' => [
            '/(?:saldo|poin|jarvis\s+points?)\s+(?:kakak|anda)?\s*(?:sudah|telah)\s+(?:kami\s+)?(?:tambahkan|isikan|topup|masukkan)(?:\s+secara\s+manual)?/ui',
            '/(?:kami\s+sudah\s+menambahkan\s+(?:saldo|poin|jarvis\s+points?))/ui',
        ],
    ];

    /**
     * Kalimat pengganti resmi yang aman dari risiko hukum/finansial.
     */
    protected string $safeReplacementText = 'Pengaduan transaksi/saldo Kakak telah dicatat oleh sistem dan saat ini sedang ditinjau oleh tim Customer Care untuk verifikasi.';

    /**
     * Inisialisasi guardrail dengan opsi status aktif dan custom patterns.
     */
    public function __construct(
        protected bool $enabled = true,
        array $customPatterns = []
    ) {
        if (! empty($customPatterns)) {
            $this->liabilityPatterns = array_merge_recursive($this->liabilityPatterns, $customPatterns);
        }
    }

    /**
     * Nama unik guardrail output.
     */
    public function name(): string
    {
        return 'financial_liability_guard';
    }

    /**
     * Status apakah guardrail ini aktif.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Evaluasi dan sanitasi teks keluaran LLM untuk mencegah komitmen finansial sepihak.
     */
    public function validateOutput(string $text, GuardrailContext $context): GuardrailResult
    {
        $currentText = trim($text);
        $customerName = $context->getCustomerName();
        $violatedCategories = [];
        $matchedSnippets = [];

        foreach ($this->liabilityPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $currentText, $matches)) {
                    $violatedCategories[] = $category;
                    $matchedSnippets = array_merge($matchedSnippets, $matches[0] ?? []);

                    // Lakukan pemotongan dan substitusi kalimat bermasalah ke kalimat aman
                    $currentText = preg_replace($pattern, $this->safeReplacementText, $currentText);
                }
            }
        }

        // Jika terdeteksi dan dimodifikasi
        if (! empty($violatedCategories)) {
            $violatedCategories = array_unique($violatedCategories);

            Log::warning('[FinancialLiabilityGuard] Financial liability hallucination detected and mitigated', [
                'conversation_id' => $context->getConversationId(),
                'customer_name' => $customerName,
                'categories' => $violatedCategories,
                'matched_snippets' => $matchedSnippets,
            ]);

            // Jika teks hasil modifikasi hanya berupa teks pengganti tanpa konteks pembuka
            if ($currentText === $this->safeReplacementText) {
                $currentText = "Halo Kak *{$customerName}*, {$this->safeReplacementText} Mohon ditunggu ya Kak.";
            }

            return GuardrailResult::modify(
                modifiedText: trim($currentText),
                reason: 'financial_liability_mitigated',
                telemetry: [
                    'categories' => $violatedCategories,
                    'matched_snippets' => $matchedSnippets,
                ]
            );
        }

        // Lolos tanpa terdeteksi komitmen finansial berbahaya
        return GuardrailResult::allow($currentText);
    }
}
