<?php

namespace App\Ai\Evals\Dto;

class EvalResult
{
    /**
     * @param  list<string>  $failureReasons
     */
    public function __construct(
        public EvalTestCase $testCase,
        public bool $isPassed,
        public float $score,
        public float $latencyMs,
        public ?string $actualOutput = null,
        public ?string $actualAction = null,
        public ?string $actualReason = null,
        public ?string $calledTool = null,
        public array $failureReasons = []
    ) {}

    /**
     * Factory untuk hasil evaluasi yang lulus (PASS).
     */
    public static function pass(
        EvalTestCase $testCase,
        float $latencyMs,
        ?string $actualOutput = null,
        ?string $actualAction = null,
        ?string $actualReason = null,
        ?string $calledTool = null
    ): self {
        return new self(
            testCase: $testCase,
            isPassed: true,
            score: 1.0,
            latencyMs: $latencyMs,
            actualOutput: $actualOutput,
            actualAction: $actualAction,
            actualReason: $actualReason,
            calledTool: $calledTool,
            failureReasons: []
        );
    }

    /**
     * Factory untuk hasil evaluasi yang gagal (FAIL).
     *
     * @param  list<string>  $failureReasons
     */
    public static function fail(
        EvalTestCase $testCase,
        float $latencyMs,
        array $failureReasons,
        ?string $actualOutput = null,
        ?string $actualAction = null,
        ?string $actualReason = null,
        ?string $calledTool = null
    ): self {
        return new self(
            testCase: $testCase,
            isPassed: false,
            score: 0.0,
            latencyMs: $latencyMs,
            actualOutput: $actualOutput,
            actualAction: $actualAction,
            actualReason: $actualReason,
            calledTool: $calledTool,
            failureReasons: $failureReasons
        );
    }

    /**
     * Ringkasan pesan kegagalan dalam satu baris.
     */
    public function getSummaryFailureMessage(): string
    {
        return implode('; ', $this->failureReasons);
    }
}
