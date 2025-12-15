<?php

namespace BlestaAi\Client\Exceptions;

/**
 * Exception thrown when rate limit is exceeded.
 *
 * This is a 429 Too Many Requests error indicating the user has exceeded
 * their allowed request rate and needs to wait before making more requests.
 */
class RateLimitException extends BlestaAiException
{
    public function __construct(
        public readonly int $limit,
        public readonly int $retryAfter,
        public readonly int $resetAt,
        string $message = '',
        int $code = 429
    ) {
        if (empty($message)) {
            $message = sprintf(
                'Rate limit exceeded. Limit: %d requests. Retry after %d seconds.',
                $limit,
                $retryAfter
            );
        }
        parent::__construct($message, $code);
    }

    /**
     * Get the number of seconds to wait before retrying.
     *
     * @return int
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Get the Unix timestamp when the rate limit resets.
     *
     * @return int
     */
    public function getResetAt(): int
    {
        return $this->resetAt;
    }

    /**
     * Get the maximum number of requests allowed.
     *
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }
}
