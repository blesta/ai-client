<?php

namespace BlestaAi\Client\Exceptions;

/**
 * Exception thrown when user has insufficient credits for the requested operation.
 *
 * This is typically a 402 Payment Required error indicating the user needs
 * to purchase more credits before continuing.
 */
class InsufficientCreditsException extends BlestaAiException
{
    public function __construct(
        public readonly float $required,
        public readonly float $available,
        string $message = '',
        int $code = 402
    ) {
        if (empty($message)) {
            $message = sprintf(
                'Insufficient credits. Required: %.4f, Available: %.4f',
                $required,
                $available
            );
        }
        parent::__construct($message, $code);
    }
}
