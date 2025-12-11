<?php

namespace BlestaAi\Client\Exceptions;

use Exception;

/**
 * Base exception for all Blesta AI API errors.
 */
class BlestaAiException extends Exception
{
    /**
     * @param string $message Error message
     * @param int $code HTTP status code or error code
     * @param Exception|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
