<?php

namespace BlestaAi\Client\Models;

use BlestaAi\Client\Exceptions\BlestaAiException;

/**
 * Represents an embeddings response from the API.
 *
 * Always carries the concrete model that produced the vectors and their
 * actual length. Vectors from different models (or different dimensions) are
 * not comparable, so store both alongside any vectors you persist.
 */
readonly class EmbeddingResponse
{
    /**
     * @param string $model Concrete model that produced the vectors (never an alias)
     * @param int $dimensions Actual vector length
     * @param array<int, array<int, float>> $embeddings Vectors keyed and ordered by input index
     * @param Usage $usage Token usage and cost information (completion tokens are always 0)
     * @param string|null $requestId Value of the X-Request-Id response header, if present
     */
    public function __construct(
        public string $model,
        public int $dimensions,
        public array $embeddings,
        public Usage $usage,
        public ?string $requestId = null
    ) {
    }

    /**
     * Create an EmbeddingResponse from API response data.
     *
     * @param array<string, mixed> $data Response body data
     * @param array<string, array<int, string>|string> $headers Response headers (optional)
     * @return self
     * Strict: throws on an empty "data" array; an index that is not an int,
     * is negative, is out of range (>= the number of items) or is duplicated;
     * an empty vector; a non-numeric value; vectors of inconsistent length;
     * or a declared "dimensions" that disagrees with the actual vector length.
     * A partially valid response is never returned, since storing mismatched
     * vectors would corrupt an index.
     *
     * @throws BlestaAiException If the body is not a valid embeddings response
     */
    public static function fromArray(array $data, array $headers = []): self
    {
        if (!isset($data['data']) || !is_array($data['data'])) {
            throw new BlestaAiException('Malformed embeddings response: missing "data" array');
        }

        if ($data['data'] === []) {
            throw new BlestaAiException('Malformed embeddings response: "data" is empty');
        }

        if (!isset($data['model']) || !is_string($data['model']) || $data['model'] === '') {
            throw new BlestaAiException('Malformed embeddings response: missing "model"');
        }

        $count = count($data['data']);
        $embeddings = [];
        $length = null;
        $position = 0;

        foreach ($data['data'] as $item) {
            if (!is_array($item) || !isset($item['embedding']) || !is_array($item['embedding'])) {
                throw new BlestaAiException(
                    'Malformed embeddings response: item ' . $position . ' has no embedding'
                );
            }

            $index = array_key_exists('index', $item) ? $item['index'] : $position;

            if (!is_int($index) || $index < 0 || $index >= $count || isset($embeddings[$index])) {
                throw new BlestaAiException(
                    'Malformed embeddings response: item ' . $position . ' has an invalid or duplicate index'
                );
            }

            if ($item['embedding'] === []) {
                throw new BlestaAiException(
                    'Malformed embeddings response: item ' . $index . ' has an empty vector'
                );
            }

            $vector = [];
            foreach ($item['embedding'] as $value) {
                if (!is_int($value) && !is_float($value)) {
                    throw new BlestaAiException(
                        'Malformed embeddings response: item ' . $index . ' contains a non-numeric value'
                    );
                }
                $vector[] = (float)$value;
            }

            if ($length === null) {
                $length = count($vector);
            } elseif (count($vector) !== $length) {
                throw new BlestaAiException('Malformed embeddings response: vectors have inconsistent lengths');
            }

            $embeddings[$index] = $vector;
            $position++;
        }

        ksort($embeddings);

        if (array_key_exists('dimensions', $data) && $data['dimensions'] !== null) {
            if (!is_int($data['dimensions']) || $data['dimensions'] !== $length) {
                throw new BlestaAiException(
                    'Malformed embeddings response: declared dimensions do not match the vector length ' . $length
                );
            }
        }

        $dimensions = (int)$length;

        return new self(
            model: $data['model'],
            dimensions: $dimensions,
            embeddings: $embeddings,
            usage: Usage::fromArray(is_array($data['usage'] ?? null) ? $data['usage'] : []),
            requestId: self::getHeaderValue($headers, 'X-Request-Id')
        );
    }

    /**
     * Get the concrete model that produced the vectors.
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the vector length.
     *
     * @return int
     */
    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * Get all vectors, keyed and ordered by input index.
     *
     * @return array<int, array<int, float>>
     */
    public function getEmbeddings(): array
    {
        return $this->embeddings;
    }

    /**
     * Get the vector for one input.
     *
     * @param int $index Zero-based position of the input in the request
     * @return array<int, float>|null Null if there is no vector at that index
     */
    public function getEmbedding(int $index): ?array
    {
        return $this->embeddings[$index] ?? null;
    }

    /**
     * Get token usage and cost information.
     *
     * @return Usage
     */
    public function getUsage(): Usage
    {
        return $this->usage;
    }

    /**
     * Get the request ID (X-Request-Id), useful when contacting support.
     *
     * @return string|null
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Convert to array representation (same shape as the API response).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->embeddings as $index => $embedding) {
            $data[] = [
                'object' => 'embedding',
                'index' => $index,
                'embedding' => $embedding,
            ];
        }

        $array = [
            'object' => 'list',
            'model' => $this->model,
            'dimensions' => $this->dimensions,
            'data' => $data,
            'usage' => $this->usage->toArray(),
        ];

        if ($this->requestId !== null) {
            $array['request_id'] = $this->requestId;
        }

        return $array;
    }

    /**
     * Get a single header value (case-insensitive). Guzzle returns header
     * values as arrays; the first value is used.
     *
     * @param array<string, array<int, string>|string> $headers
     * @param string $name
     * @return string|null
     */
    private static function getHeaderValue(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $values) {
            if (is_string($key) && strcasecmp($key, $name) === 0) {
                $value = is_array($values) ? ($values[0] ?? null) : $values;

                return is_string($value) && $value !== '' ? $value : null;
            }
        }

        return null;
    }
}
