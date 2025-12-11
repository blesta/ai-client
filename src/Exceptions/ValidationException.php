<?php

namespace BlestaAi\Client\Exceptions;

/**
 * Exception thrown when request validation fails.
 *
 * This is typically a 422 Unprocessable Entity error indicating:
 * - Invalid parameters
 * - Missing required fields
 * - Malformed request data
 */
class ValidationException extends BlestaAiException
{
    /**
     * @param array<string, mixed> $errors Validation error messages
     * @param string $message Error message
     * @param int $code HTTP status code
     */
    public function __construct(
        public readonly array $errors = [],
        string $message = 'Validation failed',
        int $code = 422
    ) {
        parent::__construct($message, $code);
    }

    /**
     * Get validation errors.
     *
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
