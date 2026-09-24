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
     * @param float|null $promptPrice Price per 1K prompt tokens (markup included)
     * @param float|null $completionPrice Price per 1K completion tokens (markup included)
     * @param int|null $contextLength Maximum context window size
     * @param string|null $type Model type: "chat" or "embedding" (null if the server does not report it)
     * @param int|null $dimensions Native vector length (embedding models only)
     * @param bool|null $supportsDimensions Whether a reduced "dimensions" option is accepted (embedding models only)
     * @param string|null $deprecatedAt ISO 8601 date the model was deprecated, or null (embedding models only)
     * @param bool|null $recommended Whether this is the recommended model of its type (embedding models only)
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description = null,
        public ?float $promptPrice = null,
        public ?float $completionPrice = null,
        public ?int $contextLength = null,
        public ?string $type = null,
        public ?int $dimensions = null,
        public ?bool $supportsDimensions = null,
        public ?string $deprecatedAt = null,
        public ?bool $recommended = null
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
            contextLength: isset($data['context_length']) ? (int)$data['context_length'] : null,
            type: isset($data['type']) ? (string)$data['type'] : null,
            dimensions: isset($data['dimensions']) ? (int)$data['dimensions'] : null,
            supportsDimensions: isset($data['supports_dimensions']) ? (bool)$data['supports_dimensions'] : null,
            deprecatedAt: isset($data['deprecated_at']) ? (string)$data['deprecated_at'] : null,
            recommended: isset($data['recommended']) ? (bool)$data['recommended'] : null
        );
    }

    /**
     * Whether this is an embedding model.
     *
     * @return bool
     */
    public function isEmbedding(): bool
    {
        return $this->type === 'embedding';
    }

    /**
     * Whether this model has been deprecated (it may still be usable during a notice period).
     *
     * @return bool
     */
    public function isDeprecated(): bool
    {
        return $this->deprecatedAt !== null;
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
            'type' => $this->type,
            'dimensions' => $this->dimensions,
            'supports_dimensions' => $this->supportsDimensions,
            'deprecated_at' => $this->deprecatedAt,
            'recommended' => $this->recommended,
        ];
    }
}
