<?php

namespace BlestaAi\Client\Models;

/**
 * Represents token usage and cost information for a chat completion.
 */
readonly class Usage
{
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
        public float $cost,
        public float $remainingBalance
    ) {
    }

    /**
     * Create Usage instance from API response data.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            promptTokens: $data['prompt_tokens'] ?? 0,
            completionTokens: $data['completion_tokens'] ?? 0,
            totalTokens: $data['total_tokens'] ?? 0,
            cost: (float)($data['cost'] ?? 0.0),
            remainingBalance: (float)($data['remaining_balance'] ?? 0.0)
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
            'cost' => $this->cost,
            'remaining_balance' => $this->remainingBalance,
        ];
    }
}
