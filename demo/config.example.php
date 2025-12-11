<?php
/**
 * Blesta AI Client Demo - Configuration Example
 *
 * Copy this file to config.php and update with your actual API key.
 *
 * SECURITY WARNING: Never commit your actual API key to version control.
 * Consider using environment variables for production deployments.
 */

// API Configuration
define('BLESTA_AI_API_KEY', 'your-api-key-here');
define('BLESTA_AI_BASE_URL', 'https://ai.blesta.com/api/v1');
define('BLESTA_AI_TIMEOUT', 60); // Increased for streaming

// For development/testing with local API
// define('BLESTA_AI_BASE_URL', 'http://localhost:3030/api/v1');

// Session Configuration
define('SESSION_NAME', 'blesta_ai_demo');
define('MAX_MESSAGES_PER_SESSION', 50);

// Rate Limiting
define('RATE_LIMIT_REQUESTS', 30); // Max requests per hour
define('RATE_LIMIT_WINDOW', 3600); // 1 hour in seconds

// Default Settings
define('DEFAULT_MODEL', 'openai/gpt-4o-mini');
define('DEFAULT_TEMPERATURE', 0.7);
define('DEFAULT_MAX_TOKENS', 1000);

// Initialize session
session_name(SESSION_NAME);
session_start();

// Initialize conversation history if not exists
if (!isset($_SESSION['messages'])) {
    $_SESSION['messages'] = [];
}

// Initialize rate limiting
if (!isset($_SESSION['rate_limit'])) {
    $_SESSION['rate_limit'] = [
        'count' => 0,
        'reset_time' => time() + RATE_LIMIT_WINDOW
    ];
}

// Check if API key is configured
function isConfigured(): bool
{
    return BLESTA_AI_API_KEY !== 'your-api-key-here' && !empty(BLESTA_AI_API_KEY);
}

// Check rate limit
function checkRateLimit(): bool
{
    if (time() > $_SESSION['rate_limit']['reset_time']) {
        $_SESSION['rate_limit'] = [
            'count' => 0,
            'reset_time' => time() + RATE_LIMIT_WINDOW
        ];
    }

    return $_SESSION['rate_limit']['count'] < RATE_LIMIT_REQUESTS;
}

// Increment rate limit counter
function incrementRateLimit(): void
{
    $_SESSION['rate_limit']['count']++;
}

// CSRF Protection
function generateCSRFToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
