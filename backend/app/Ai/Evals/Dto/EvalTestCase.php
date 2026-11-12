<?php

namespace App\Ai\Evals\Dto;

class EvalTestCase
{
    /**
     * @param  array<string, mixed>  $assertions
     */
    public function __construct(
        public string $id,
        public string $category,
        public string $tier,
        public string $prompt,
        public array $assertions = []
    ) {}

    /**
     * Factory instance dari array data JSON.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            category: (string) ($data['category'] ?? ''),
            tier: (string) ($data['tier'] ?? 'semantic'),
            prompt: (string) ($data['prompt'] ?? ''),
            assertions: (array) ($data['assertions'] ?? [])
        );
    }

    public function isGuardrail(): bool
    {
        return $this->tier === 'guardrail';
    }

    public function isSemantic(): bool
    {
        return $this->tier === 'semantic';
    }

    public function getExpectedAction(): string
    {
        return (string) ($this->assertions['expected_action'] ?? 'allow');
    }

    public function getExpectedReason(): ?string
    {
        $val = $this->assertions['expected_reason'] ?? null;

        return $val !== null ? (string) $val : null;
    }

    /**
     * @return list<string>
     */
    public function getMustContain(): array
    {
        return (array) ($this->assertions['must_contain'] ?? []);
    }

    /**
     * @return list<string>
     */
    public function getMustNotContain(): array
    {
        return (array) ($this->assertions['must_not_contain'] ?? []);
    }

    public function getExpectedTool(): string
    {
        return (string) ($this->assertions['expected_tool'] ?? 'none');
    }

    public function getExpectedTicketCategory(): ?string
    {
        $val = $this->assertions['expected_ticket_category'] ?? null;

        return $val !== null ? (string) $val : null;
    }
}
