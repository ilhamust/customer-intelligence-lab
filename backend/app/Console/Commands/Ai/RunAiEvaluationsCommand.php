<?php

namespace App\Console\Commands\Ai;

use App\Ai\Evals\AiEvaluatorService;
use App\Ai\Evals\Dto\EvalResult;
use App\Ai\Evals\Dto\EvalTestCase;
use App\Ai\Evals\Formatters\MarkdownReportFormatter;
use Illuminate\Console\Command;
use Throwable;

class RunAiEvaluationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:evaluate
                            {--mode=guardrail-first : Mode eksekusi (guardrail-first, guardrails, semantic, full)}
                            {--category= : Filter kategori kasus tertentu (faq, typo, out_of_scope, prompt_injection, emergency_safety, need_human, points_history, output_sanitizer)}
                            {--threshold=95 : Ambang batas akurasi kelulusan (%)}
                            {--report : Simpan dokumen laporan evaluasi resmi berformat Markdown}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menjalankan uji evaluasi otomatis AI (Guardrails & LLM Semantik) untuk mengukur skor akurasi formal sebelum UAT';

    /**
     * Execute the console command.
     */
    public function handle(
        AiEvaluatorService $evaluator,
        MarkdownReportFormatter $reportFormatter
    ): int {
        $startTime = microtime(true);

        $mode = (string) ($this->option('mode') ?: 'guardrail-first');
        $categoryFilter = $this->option('category') ? (string) $this->option('category') : null;
        $threshold = (float) ($this->option('threshold') ?: 95.0);
        $generateReport = (bool) $this->option('report');

        try {
            $allCases = $evaluator->loadDataset();
        } catch (Throwable $e) {
            $this->error('Gagal memuat dataset: ' . $e->getMessage());

            return Command::FAILURE;
        }

        // Filter kasus uji berdasarkan opsi mode dan kategori
        $selectedCases = $this->filterCases($allCases, $mode, $categoryFilter);

        $totalCases = count($selectedCases);
        if ($totalCases === 0) {
            $this->warn('Tidak ada kasus uji yang cocok dengan filter yang ditentukan.');

            return Command::SUCCESS;
        }

        $this->outputHeader($mode, $categoryFilter, $threshold, $totalCases);

        $results = [];
        $categoryStats = [];

        $progressBar = $this->output->createProgressBar($totalCases);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $progressBar->setMessage('Memulai pengujian...');
        $progressBar->start();

        foreach ($selectedCases as $index => $case) {
            $progressBar->setMessage("Menguji {$case->id} ({$case->category})...");

            $res = $evaluator->evaluate($case);
            $results[] = $res;

            // Inisialisasi statistik kategori jika belum ada
            if (! isset($categoryStats[$case->category])) {
                $categoryStats[$case->category] = [
                    'total' => 0,
                    'pass' => 0,
                    'fail' => 0,
                    'latency_sum' => 0.0,
                ];
            }

            $categoryStats[$case->category]['total']++;
            if ($res->isPassed) {
                $categoryStats[$case->category]['pass']++;
            } else {
                $categoryStats[$case->category]['fail']++;
            }
            $categoryStats[$case->category]['latency_sum'] += $res->latencyMs;

            $progressBar->advance();

            // Beri jeda 1 detik jika kasus memanggil LLM nyata untuk mencegah rate-limit provider
            if ($case->isSemantic() && $index < ($totalCases - 1)) {
                usleep(1000000);
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $durationSec = round(microtime(true) - $startTime, 2);

        // Tampilkan Tabel Hasil Rekapitulasi per Kategori
        $this->displaySummaryTable($categoryStats, $threshold);

        // Kalkulasi Skor Keseluruhan
        $passCount = count(array_filter($results, fn (EvalResult $r) => $r->isPassed));
        $failCount = $totalCases - $passCount;
        $overallAccuracy = round(($passCount / $totalCases) * 100, 2);
        $isPassed = $overallAccuracy >= $threshold;

        $this->outputOverallResult($totalCases, $passCount, $failCount, $overallAccuracy, $threshold, $durationSec, $isPassed);

        // Tampilkan rincian kasus yang gagal (jika ada)
        if ($failCount > 0) {
            $this->displayFailedCases($results);
        }

        // Simpan dokumen laporan Markdown jika flag --report disertakan
        if ($generateReport) {
            $reportMeta = [
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'mode' => $mode,
                'threshold' => $threshold,
                'duration_sec' => $durationSec,
            ];

            $markdownContent = $reportFormatter->generate($results, $categoryStats, $reportMeta);
            $savedPath = $reportFormatter->save($markdownContent);

            $this->info("[OK] Dokumen laporan evaluasi resmi disimpan ke: {$savedPath}");
        }

        return $isPassed ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Filter kasus uji sesuai mode eksekusi dan filter kategori.
     *
     * @param  list<EvalTestCase>  $cases
     * @return list<EvalTestCase>
     */
    protected function filterCases(array $cases, string $mode, ?string $categoryFilter): array
    {
        // 1. Jika ada filter kategori eksplisit, prioritaskan kategori tersebut
        if ($categoryFilter) {
            return array_values(array_filter($cases, fn (EvalTestCase $c) => $c->category === $categoryFilter));
        }

        // 2. Filter berdasarkan mode eksekusi
        return match ($mode) {
            'guardrails' => array_values(array_filter($cases, fn (EvalTestCase $c) => $c->isGuardrail())),
            'semantic' => array_values(array_filter($cases, fn (EvalTestCase $c) => $c->isSemantic())),
            'full' => $cases,
            'guardrail-first' => $this->pickGuardrailFirstSubset($cases),
            default => $cases,
        };
    }

    /**
     * Memilih subset terukur untuk mode Guardrail-First:
     * Seluruh kasus Guardrail (21 kasus) + 7 kasus representatif Semantik.
     *
     * @param  list<EvalTestCase>  $cases
     * @return list<EvalTestCase>
     */
    protected function pickGuardrailFirstSubset(array $cases): array
    {
        $selected = [];
        $representativeSemanticIds = [
            'FAQ-001', // Jam buka Sunset Road
            'FAQ-003', // Konektor CCS2
            'TYPO-001', // Salah ketik jam buka
            'OOS-001', // Out of scope koding
            'OOS-002', // Out of scope resep
            'PTS-001', // Tool riwayat poin
            'HUM-004', // Tool eskalasi tiket transaksi
        ];

        foreach ($cases as $c) {
            if ($c->isGuardrail() || in_array($c->id, $representativeSemanticIds, true)) {
                $selected[] = $c;
            }
        }

        return $selected;
    }

    protected function outputHeader(string $mode, ?string $category, float $threshold, int $totalCases): void
    {
        $this->line('====================================================================');
        $this->info(' JARVIS AI EVALUATION & BENCHMARK HARNESS (ITEM A10)');
        $this->line('====================================================================');
        $this->line(" Mode Eksekusi : <comment>{$mode}</comment>");
        if ($category) {
            $this->line(" Kategori      : <comment>{$category}</comment>");
        }
        $this->line(" Target Akurasi: <comment>{$threshold}%</comment>");
        $this->line(" Jumlah Kasus  : <comment>{$totalCases} kasus</comment>");
        $this->line('====================================================================');
        $this->newLine();
    }

    /**
     * Tampilkan tabel ringkasan per kategori di terminal.
     *
     * @param  array<string, array{total: int, pass: int, fail: int, latency_sum: float}>  $categoryStats
     */
    protected function displaySummaryTable(array $categoryStats, float $threshold): void
    {
        $tableRows = [];
        $idx = 1;

        foreach ($categoryStats as $category => $stats) {
            $total = $stats['total'];
            $pass = $stats['pass'];
            $fail = $stats['fail'];
            $acc = $total > 0 ? round(($pass / $total) * 100, 1) : 0.0;
            $avgLatency = $total > 0 ? round($stats['latency_sum'] / $total, 2) : 0.0;

            $statusText = $acc >= $threshold ? "<info>{$acc}%</info>" : "<error>{$acc}%</error>";

            $tableRows[] = [
                $idx++,
                $category,
                $total,
                $pass,
                $fail,
                $statusText,
                "{$avgLatency} ms",
            ];
        }

        $this->table(
            ['#', 'Kategori', 'Total', 'PASS', 'FAIL', 'Akurasi (%)', 'Rata2 Latensi'],
            $tableRows
        );
    }

    protected function outputOverallResult(
        int $total,
        int $pass,
        int $fail,
        float $accuracy,
        float $threshold,
        float $durationSec,
        bool $isPassed
    ): void {
        $this->newLine();
        $this->line('--------------------------------------------------------------------');
        $this->line("Total Kasus Dievaluasi : {$total}");
        $this->line("Lulus (PASS)           : <info>{$pass}</info>");
        $this->line("Gagal (FAIL)           : " . ($fail > 0 ? "<error>{$fail}</error>" : '0'));
        $this->line("Akurasi Keseluruhan    : <bold>{$accuracy}%</bold> (Ambang: {$threshold}%)");
        $this->line("Waktu Eksekusi         : {$durationSec} detik");
        $this->line('--------------------------------------------------------------------');

        if ($isPassed) {
            $this->info('STATUS KESIAPAN: ✅ PASS / READY FOR UAT');
        } else {
            $this->error("STATUS KESIAPAN: ❌ FAIL (Akurasi di bawah target ambang {$threshold}%)");
        }
        $this->newLine();
    }

    /**
     * @param  list<EvalResult>  $results
     */
    protected function displayFailedCases(array $results): void
    {
        $this->error('RINCIAN KASUS GAGAL:');
        foreach ($results as $r) {
            if (! $r->isPassed) {
                $this->line(" - [{$r->testCase->id}] {$r->testCase->prompt}");
                $this->line("   Alasan: <comment>{$r->getSummaryFailureMessage()}</comment>");
            }
        }
        $this->newLine();
    }
}
