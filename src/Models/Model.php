<?php

namespace BlestaAi\Client\Models;

/**
 * Represents an AI model available through the API.
 */
readonly class Model
{
    /**
     * @param string $id Model identifier (e.g., "openai/gpt-4")
     * @param string $name Human-readable model name
     * @param string|null $description Model description
     * @param float|null $promptPrice Price per 1M prompt tokens
     * @param float|null $completionPrice Price per 1M completion tokens
     * @param int|null $contextLength Maximum context window size
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description = null,
        public ?float $promptPrice = null,
        public ?float $completionPrice = null,
        public ?int $contextLength = null
    ) {
    }

    /**
     * Create Model instance from API response data.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $pricing = $data['pricing'] ?? [];

        return new self(
            id: $data['id'] ?? '',
            name: $data['name'] ?? $data['id'] ?? '',
            description: $data['description'] ?? null,
            promptPrice: isset($pricing['prompt']) ? (float)$pricing['prompt'] : null,
            completionPrice: isset($pricing['completion']) ? (float)$pricing['completion'] : null,
            contextLength: isset($data['context_length']) ? (int)$data['context_length'] : null
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
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'pricing' => [
                'prompt' => $this->promptPrice,
                'completion' => $this->completionPrice,
            ],
            'context_length' => $this->contextLength,
        ];
    }
}
