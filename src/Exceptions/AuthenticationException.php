<?php

namespace BlestaAi\Client\Exceptions;

/**
 * Exception thrown when API key authentication fails.
 *
 * This is typically a 401 Unauthorized error indicating:
 * - Missing API key
 * - Invalid API key
 * - Inactive API key
 */
class AuthenticationException extends BlestaAiException
{
}
