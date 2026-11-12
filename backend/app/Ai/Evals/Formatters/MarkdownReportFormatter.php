<?php

namespace App\Ai\Evals\Formatters;

use App\Ai\Evals\Dto\EvalResult;
use Illuminate\Support\Facades\File;

class MarkdownReportFormatter
{
    /**
     * Menghasilkan teks dokumen laporan evaluasi berformat Markdown terstruktur.
     *
     * @param  list<EvalResult>  $results
     * @param  array<string, array{total: int, pass: int, fail: int, latency_sum: float}>  $categoryStats
     * @param  array<string, mixed>  $meta
     */
    public function generate(array $results, array $categoryStats, array $meta): string
    {
        $timestamp = $meta['timestamp'] ?? now()->toIso8601String();
        $mode = $meta['mode'] ?? 'guardrail-first';
        $threshold = (float) ($meta['threshold'] ?? 95.0);
        $totalCases = count($results);
        $passCount = count(array_filter($results, fn (EvalResult $r) => $r->isPassed));
        $failCount = $totalCases - $passCount;
        $overallAccuracy = $totalCases > 0 ? round(($passCount / $totalCases) * 100, 2) : 0.0;
        $isPassedOverall = $overallAccuracy >= $threshold;
        $statusBadge = $isPassedOverall ? '✅ PASS / READY FOR UAT' : '❌ FAIL';
        $durationSec = round((float) ($meta['duration_sec'] ?? 0.0), 2);

        $md = [];
        $md[] = '# Laporan Hasil Evaluasi Formal AI Support & Guardrails (Jarvis EV Charging)';
        $md[] = '';
        $md[] = '> **Status Kesiapan UAT:** ' . $statusBadge;
        $md[] = '';
        $md[] = '## 1. Metadata Pengujian';
        $md[] = "- **Waktu Pengujian:** {$timestamp}";
        $md[] = "- **Mode Eksekusi:** `{$mode}`";
        $md[] = "- **Total Durasi:** {$durationSec} detik";
        $md[] = "- **Target Ambang Batas Akurasi (Threshold):** {$threshold}%";
        $md[] = "- **Skor Akurasi Keseluruhan:** **{$overallAccuracy}%** ({$passCount}/{$totalCases} kasus lulus)";
        $md[] = '';
        $md[] = '---';
        $md[] = '';
        $md[] = '## 2. Rekapitulasi Akurasi per Kategori';
        $md[] = '| # | Kategori Kasus | Total | PASS | FAIL | Akurasi (%) | Rata-rata Latensi |';
        $md[] = '|---|---|:---:|:---:|:---:|:---:|:---:|';

        $idx = 1;
        foreach ($categoryStats as $category => $stats) {
            $catTotal = $stats['total'];
            $catPass = $stats['pass'];
            $catFail = $stats['fail'];
            $catAcc = $catTotal > 0 ? round(($catPass / $catTotal) * 100, 1) : 0.0;
            $catAvgLat = $catTotal > 0 ? round($stats['latency_sum'] / $catTotal, 2) : 0.0;

            $statusIcon = $catAcc >= $threshold ? '✅' : ($catAcc >= 80 ? '⚠️' : '❌');

            $md[] = "| {$idx} | `{$category}` | {$catTotal} | {$catPass} | {$catFail} | {$statusIcon} {$catAcc}% | {$catAvgLat} ms |";
            $idx++;
        }

        $md[] = '';
        $md[] = '---';
        $md[] = '';
        $md[] = '## 3. Rincian Kasus Uji';
        $md[] = '| ID | Kategori | Tier | Status | Latensi | Input Prompt | Catatan / Alasan Kegagalan |';
        $md[] = '|---|---|---|:---:|:---:|---|---|';

        foreach ($results as $r) {
            $c = $r->testCase;
            $status = $r->isPassed ? '✅ PASS' : '❌ FAIL';
            $promptClean = str_replace(["\r", "\n", '|'], ['', '', '/'], $c->prompt);
            $notes = $r->isPassed
                ? ($r->calledTool ? "Tool: `{$r->calledTool}`" : ($r->actualAction ? "Action: `{$r->actualAction}`" : 'OK'))
                : str_replace(["\r", "\n", '|'], ['', '', '/'], $r->getSummaryFailureMessage());

            $md[] = "| `{$c->id}` | `{$c->category}` | `{$c->tier}` | {$status} | {$r->latencyMs} ms | {$promptClean} | {$notes} |";
        }

        $md[] = '';
        $md[] = '---';
        $md[] = '*Dokumen ini dibuat otomatis oleh Laravel Artisan AI Evaluation Harness (`php artisan ai:evaluate`).*';
        $md[] = '';

        return implode("\n", $md);
    }

    /**
     * Menyimpan dokumen laporan ke storage/app/evals/.
     *
     * @return string Path absolut file laporan yang disimpan
     */
    public function save(string $content, ?string $customFilename = null): string
    {
        $dir = storage_path('app/evals');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        $filename = $customFilename ?: 'ai-eval-report-'.date('Y-m-d-His').'.md';
        $fullPath = $dir.DIRECTORY_SEPARATOR.$filename;

        file_put_contents($fullPath, $content);

        return $fullPath;
    }
}
