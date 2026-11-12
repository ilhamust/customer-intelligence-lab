<?php

namespace App\Ai\Evals;

use App\Ai\Agents\CustomerSupportAgent;
use App\Ai\Evals\Dto\EvalResult;
use App\Ai\Evals\Dto\EvalTestCase;
use App\Ai\Guardrails\Dto\GuardrailContext;
use App\Ai\Guardrails\GuardrailManager;
use App\Models\Bot\Conversation;
use App\Models\Bot\Customer;
use App\Models\Bot\Message;
use App\Models\Bot\Ticket;
use App\Models\Loyalty\PointsLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class AiEvaluatorService
{
    public function __construct(
        protected GuardrailManager $guardrailManager
    ) {}

    /**
     * Membaca dan mem-parsing dataset evaluasi terstandar dari file JSON.
     *
     * @return list<EvalTestCase>
     */
    public function loadDataset(?string $customPath = null): array
    {
        $filePath = $customPath ?: base_path('tests/Datasets/ai_eval_dataset.json');

        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("File dataset evaluasi tidak ditemukan: {$filePath}");
        }

        $raw = file_get_contents($filePath);
        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded) || ! isset($decoded['test_cases']) || ! is_array($decoded['test_cases'])) {
            throw new InvalidArgumentException("Format file dataset evaluasi tidak valid pada: {$filePath}");
        }

        $cases = [];
        foreach ($decoded['test_cases'] as $item) {
            if (is_array($item)) {
                $cases[] = EvalTestCase::fromArray($item);
            }
        }

        return $cases;
    }

    /**
     * Mengevaluasi satu kasus uji (otomatis mendeteksi Tier Guardrail vs Semantic).
     */
    public function evaluate(EvalTestCase $testCase, ?string $provider = null): EvalResult
    {
        if ($testCase->isGuardrail()) {
            return $this->evaluateGuardrail($testCase);
        }

        return $this->evaluateSemantic($testCase, $provider);
    }

    /**
     * Evaluasi kasus uji Guardrail (100% offline, cepat, nol kuota API LLM).
     */
    public function evaluateGuardrail(EvalTestCase $testCase): EvalResult
    {
        $startTime = microtime(true);
        $failures = [];

        // Kasus uji Output Sanitizer (Post-LLM)
        if ($testCase->category === 'output_sanitizer') {
            $context = new GuardrailContext();
            $outputResult = $this->guardrailManager->validateOutput($testCase->prompt, $context);

            $actualOutput = $outputResult->getModifiedText();
            $actualAction = $outputResult->getAction();
            $actualReason = $outputResult->getReason();

            // Verifikasi kewajiban kata kunci
            foreach ($testCase->getMustContain() as $term) {
                if (! str_contains(mb_strtolower($actualOutput), mb_strtolower($term))) {
                    $failures[] = "Output sanitasi tidak memuat kata kunci wajib: '{$term}'";
                }
            }

            // Verifikasi larangan kata terlarang
            foreach ($testCase->getMustNotContain() as $term) {
                if (str_contains(mb_strtolower($actualOutput), mb_strtolower($term))) {
                    $failures[] = "Output sanitasi masih memuat kata terlarang: '{$term}'";
                }
            }

            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            if (empty($failures)) {
                return EvalResult::pass(
                    testCase: $testCase,
                    latencyMs: $latencyMs,
                    actualOutput: $actualOutput,
                    actualAction: $actualAction,
                    actualReason: $actualReason
                );
            }

            return EvalResult::fail(
                testCase: $testCase,
                latencyMs: $latencyMs,
                failureReasons: $failures,
                actualOutput: $actualOutput,
                actualAction: $actualAction,
                actualReason: $actualReason
            );
        }

        // Kasus uji Input Guardrail (Pre-LLM)
        $dummyCustomer = new Customer([
            'name' => 'Budi Santoso',
            'phone' => '628123456789',
        ]);

        $dummyConversation = new Conversation([
            'phone' => '628123456789',
            'name' => 'Budi Santoso',
            'handler' => 'ai',
        ]);

        $dummyMessage = new Message([
            'text' => $testCase->prompt,
            'is_from_me' => false,
        ]);

        $context = new GuardrailContext(
            conversation: $dummyConversation,
            customer: $dummyCustomer,
            incomingMessage: $dummyMessage
        );

        $result = $this->guardrailManager->validateInput($testCase->prompt, $context);

        $actualAction = 'allow';
        if ($result->isBlocked()) {
            $actualAction = 'block';
        } elseif ($result->isBypassed()) {
            $actualAction = 'bypass';
        }

        $actualReason = $result->getReason();
        $actualOutput = $result->getModifiedText();

        // 1. Verifikasi kecocokan Action
        if ($actualAction !== $testCase->getExpectedAction()) {
            $failures[] = "Aksi guardrail tidak cocok: diharapkan '{$testCase->getExpectedAction()}', didapatkan '{$actualAction}'";
        }

        // 2. Verifikasi kecocokan Alasan (Reason)
        if ($testCase->getExpectedReason() !== null && $actualReason !== $testCase->getExpectedReason()) {
            $failures[] = "Alasan guardrail tidak cocok: diharapkan '{$testCase->getExpectedReason()}', didapatkan '{$actualReason}'";
        }

        // 3. Verifikasi kata kunci wajib (must_contain)
        foreach ($testCase->getMustContain() as $term) {
            if (! str_contains(mb_strtolower($actualOutput), mb_strtolower($term))) {
                $failures[] = "Respon guardrail tidak memuat kata kunci wajib: '{$term}'";
            }
        }

        // 4. Verifikasi kata terlarang (must_not_contain)
        foreach ($testCase->getMustNotContain() as $term) {
            if (str_contains(mb_strtolower($actualOutput), mb_strtolower($term))) {
                $failures[] = "Respon guardrail memuat kata terlarang: '{$term}'";
            }
        }

        $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

        if (empty($failures)) {
            return EvalResult::pass(
                testCase: $testCase,
                latencyMs: $latencyMs,
                actualOutput: $actualOutput,
                actualAction: $actualAction,
                actualReason: $actualReason
            );
        }

        return EvalResult::fail(
            testCase: $testCase,
            latencyMs: $latencyMs,
            failureReasons: $failures,
            actualOutput: $actualOutput,
            actualAction: $actualAction,
            actualReason: $actualReason
        );
    }

    /**
     * Evaluasi kasus uji Semantik & Tool Calling ke LLM nyata.
     * Menggunakan transaksi database (DB::rollBack) agar zero-pollution ke data operasional.
     */
    public function evaluateSemantic(EvalTestCase $testCase, ?string $provider = null): EvalResult
    {
        $startTime = microtime(true);
        $failures = [];
        $calledTool = null;
        $actualReplyText = '';

        DB::beginTransaction();

        try {
            // Siapkan mock Customer
            /** @var Customer $customer */
            $customer = Customer::create([
                'phone' => '628129999'.rand(1000, 9999),
                'name' => 'Budi Santoso',
                'customer_code' => 'JRV-TEST-01',
                'vehicle' => 'BYD M6',
                'plate_number' => 'DK 1234 EV',
                'driver_type' => 'personal',
                'residential_area' => 'Sunset Road',
                'primary_route' => 'Denpasar - Kuta',
                'points_balance' => 2500,
                'is_marketing_consent' => true,
                'status' => 'active',
            ]);

            // Siapkan mock Conversation
            /** @var Conversation $conversation */
            $conversation = Conversation::create([
                'phone' => $customer->phone,
                'name' => $customer->name,
                'handler' => 'ai',
                'customer_id' => $customer->id,
            ]);

            // Tambahkan data mutasi points_ledger jika sedang menguji tool riwayat poin
            if ($testCase->getExpectedTool() === 'get_customer_points_history') {
                PointsLedger::create([
                    'customer_id' => $customer->id,
                    'type' => 'earn',
                    'points' => 250,
                    'balance_after' => 2500,
                    'description' => 'Pengisian daya 25 kWh di Sunset Road',
                    'created_at' => now()->subDay(),
                ]);
            }

            // Inisialisasi CustomerSupportAgent
            $agent = new CustomerSupportAgent($conversation);
            $response = $agent->prompt($testCase->prompt);
            $rawReplyText = (string) $response->text;

            // Sanitasi output melalui WhatsAppSanitizerGuard
            $guardrailContext = new GuardrailContext($conversation, $customer);
            $outputResult = $this->guardrailManager->validateOutput($rawReplyText, $guardrailContext);
            $actualReplyText = $outputResult->getModifiedText();

            // Deteksi pemanggilan Tool
            /** @var Ticket|null $ticket */
            $ticket = Ticket::where('conversation_id', $conversation->id)->latest()->first();
            if ($ticket) {
                $calledTool = 'create_support_ticket';
            } elseif (
                str_contains(mb_strtolower($actualReplyText), 'mutasi') ||
                str_contains(mb_strtolower($actualReplyText), '25 kwh') ||
                str_contains(mb_strtolower($actualReplyText), 'riwayat poin') ||
                str_contains(mb_strtolower($actualReplyText), 'points ledger')
            ) {
                $calledTool = 'get_customer_points_history';
            } else {
                $calledTool = 'none';
            }

            // 1. Verifikasi pemanggilan tool yang diharapkan
            if ($testCase->getExpectedTool() !== 'none') {
                if ($calledTool !== $testCase->getExpectedTool()) {
                    $failures[] = "Tool yang dipanggil tidak sesuai: diharapkan '{$testCase->getExpectedTool()}', didapatkan '{$calledTool}'";
                }

                if ($ticket && $testCase->getExpectedTicketCategory() !== null && $ticket->category !== $testCase->getExpectedTicketCategory()) {
                    $failures[] = "Kategori tiket tidak sesuai: diharapkan '{$testCase->getExpectedTicketCategory()}', didapatkan '{$ticket->category}'";
                }
            }

            // 2. Verifikasi kata kunci wajib (must_contain)
            foreach ($testCase->getMustContain() as $term) {
                if (! str_contains(mb_strtolower($actualReplyText), mb_strtolower($term))) {
                    $failures[] = "Respon AI tidak memuat kata kunci wajib: '{$term}'";
                }
            }

            // 3. Verifikasi kata terlarang (must_not_contain)
            foreach ($testCase->getMustNotContain() as $term) {
                if (str_contains(mb_strtolower($actualReplyText), mb_strtolower($term))) {
                    $failures[] = "Respon AI memuat kata terlarang: '{$term}'";
                }
            }

            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            if (empty($failures)) {
                return EvalResult::pass(
                    testCase: $testCase,
                    latencyMs: $latencyMs,
                    actualOutput: $actualReplyText,
                    actualAction: $testCase->getExpectedAction(),
                    calledTool: $calledTool
                );
            }

            return EvalResult::fail(
                testCase: $testCase,
                latencyMs: $latencyMs,
                failureReasons: $failures,
                actualOutput: $actualReplyText,
                actualAction: $testCase->getExpectedAction(),
                calledTool: $calledTool
            );

        } catch (Throwable $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            return EvalResult::fail(
                testCase: $testCase,
                latencyMs: $latencyMs,
                failureReasons: ['Exception error: '.$e->getMessage()],
                actualOutput: $actualReplyText,
                calledTool: $calledTool
            );
        } finally {
            DB::rollBack();
        }
    }
}
