<?php

namespace BlestaAi\Client\Models;

/**
 * Represents a chat completion response from the API.
 */
readonly class ChatCompletion
{
    /**
     * @param string $id Unique identifier for the completion
     * @param string $model Model used for the completion
     * @param array<int, array<string, mixed>> $choices Array of completion choices
     * @param Usage $usage Token usage and cost information
     * @param int $created Unix timestamp of creation
     */
    public function __construct(
        public string $id,
        public string $model,
        public array $choices,
        public Usage $usage,
        public int $created
    ) {
    }

    /**
     * Create ChatCompletion instance from API response data.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            model: $data['model'] ?? '',
            choices: $data['choices'] ?? [],
            usage: Usage::fromArray($data['usage'] ?? []),
            created: $data['created'] ?? time()
        );
    }

    /**
     * Get the first completion message content.
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->choices[0]['message']['content'] ?? '';
    }

    /**
     * Get the finish reason for the first choice.
     *
     * @return string
     */
    public function getFinishReason(): string
    {
        return $this->choices[0]['finish_reason'] ?? '';
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
            'model' => $this->model,
            'choices' => $this->choices,
            'usage' => $this->usage->toArray(),
            'created' => $this->created,
        ];
    }
}
