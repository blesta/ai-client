<?php

namespace BlestaAi\Client\Models;

/**
 * Represents rate limit information from API response headers.
 */
readonly class RateLimit
{
    public function __construct(
        public int $limit,
        public int $remaining,
        public int $reset
    ) {
    }

    /**
     * Create RateLimit instance from response headers.
     *
     * @param array<string, array<int, string>> $headers Response headers
     * @return self|null Returns null if rate limit headers are not present
     */
    public static function fromHeaders(array $headers): ?self
    {
        $limit = self::getHeaderValue($headers, 'X-RateLimit-Limit');
        $remaining = self::getHeaderValue($headers, 'X-RateLimit-Remaining');
        $reset = self::getHeaderValue($headers, 'X-RateLimit-Reset');

        // If any header is missing, return null (rate limiting may be disabled)
        if ($limit === null || $remaining === null || $reset === null) {
            return null;
        }

        return new self(
            limit: (int)$limit,
            remaining: (int)$remaining,
            reset: (int)$reset
        );
    }

    /**
     * Get a single header value from headers array.
     *
     * Guzzle returns headers as arrays of values, we want the first value.
     *
     * @param array<string, array<int, string>> $headers
     * @param string $name
     * @return string|null
     */
    private static function getHeaderValue(array $headers, string $name): ?string
    {
        // Case-insensitive header search
        foreach ($headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                return $values[0] ?? null;
            }
        }
        return null;
    }

    /**
     * Get the number of seconds until the rate limit resets.
     *
     * @return int
     */
    public function getSecondsUntilReset(): int
    {
        $now = time();
        return max(0, $this->reset - $now);
    }

    /**
     * Check if rate limit is close to being exceeded.
     *
     * @param float $threshold Percentage threshold (0.0 to 1.0). Default: 0.1 (10%)
     * @return bool True if remaining requests are below threshold
     */
    public function isNearLimit(float $threshold = 0.1): bool
    {
        if ($this->limit === 0) {
            return false;
        }

        $percentageRemaining = $this->remaining / $this->limit;
        return $percentageRemaining <= $threshold;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'limit' => $this->limit,
            'remaining' => $this->remaining,
            'reset' => $this->reset,
            'reset_in_seconds' => $this->getSecondsUntilReset(),
        ];
    }
}
