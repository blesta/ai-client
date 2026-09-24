<?php

/**
 * Example: Rate Limit Testing
 *
 * This example demonstrates how to handle rate limits and monitor rate limit status.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BlestaAi\Client\BlestaAiClient;
use BlestaAi\Client\Exceptions\RateLimitException;
use BlestaAi\Client\Exceptions\BlestaAiException;

// Replace with your actual API key
$apiKey = 'sk_your_api_key_here';

// For development/testing with local server
$client = new BlestaAiClient($apiKey, 'http://localhost:3030/api/v1');

echo "Testing Rate Limit Functionality\n";
echo str_repeat("=", 50) . "\n\n";

try {
    // Make multiple requests to monitor rate limit
    for ($i = 1; $i <= 5; $i++) {
        echo "Request #{$i}:\n";

        try {
            $response = $client->chatCompletion('x-ai/grok-4-fast', [
                ['role' => 'user', 'content' => 'Say hello #' . $i]
            ]);

            echo "  ✓ Response: {$response->getContent()}\n";

            // Display rate limit status
            if ($response->rateLimit !== null) {
                echo "  Rate Limit: {$response->rateLimit->remaining}/{$response->rateLimit->limit} remaining\n";
                echo "  Resets in: {$response->rateLimit->getSecondsUntilReset()}s\n";

                if ($response->rateLimit->isNearLimit(0.3)) {
                    echo "WARNING: Approaching rate limit threshold!\n";
                }
            } else {
                echo "  (Rate limiting not enabled)\n";
            }

        } catch (RateLimitException $e) {
            echo "    RATE LIMIT EXCEEDED!\n";
            echo "    Message: {$e->getMessage()}\n";
            echo "    Retry after: {$e->getRetryAfter()} seconds\n";
            echo "    Reset at: " . date('H:i:s', $e->getResetAt()) . "\n";

            // Wait for rate limit to reset
            echo "\n  Waiting {$e->getRetryAfter()} seconds before continuing...\n";
            sleep($e->getRetryAfter() + 1);
            echo "  Resuming...\n\n";

            // Retry the request
            $i--;
            continue;
        }

        echo "\n";

        // Small delay between requests
        usleep(100000); // 0.1 second
    }

    echo str_repeat("=", 50) . "\n";
    echo "Test completed successfully!\n";

} catch (BlestaAiException $e) {
    echo "\nAPI Error: {$e->getMessage()}\n";
    echo "Code: {$e->getCode()}\n";
} catch (Exception $e) {
    echo "\nUnexpected error: {$e->getMessage()}\n";
}
