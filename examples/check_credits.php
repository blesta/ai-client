<?php

/**
 * Example: Check Credit Balance
 *
 * This example demonstrates how to check your current credit balance.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BlestaAi\Client\BlestaAiClient;
use BlestaAi\Client\Exceptions\AuthenticationException;
use BlestaAi\Client\Exceptions\BlestaAiException;

// Replace with your actual API key
$apiKey = 'sk_your_api_key_here';

// For development/testing, you can use localhost
// $client = new BlestaAiClient($apiKey, 'http://localhost:3030/api/v1');

// For production
$client = new BlestaAiClient($apiKey);

try {
    echo "Checking credit balance...\n\n";

    $balance = $client->getCredits();

    echo "Current Balance: $" . number_format($balance, 4) . "\n\n";

    // Provide some context about the balance
    if ($balance <= 0) {
        echo "⚠️  Your balance is empty. Please add credits to continue using the API.\n";
    } elseif ($balance < 1.00) {
        echo "⚠️  Low balance warning! Consider adding more credits soon.\n";
    } elseif ($balance < 10.00) {
        echo "✓ You have a modest balance available.\n";
    } else {
        echo "✓ You have a healthy balance available.\n";
    }

    // Example: Estimate how many requests you can make
    // Assuming average cost of $0.01 per request (this will vary by model and usage)
    $avgCostPerRequest = 0.01;
    $estimatedRequests = floor($balance / $avgCostPerRequest);

    echo "\nEstimated requests remaining: ~" . number_format($estimatedRequests) . "\n";
    echo "(Based on estimated average cost of $" . number_format($avgCostPerRequest, 2) . " per request)\n";

} catch (AuthenticationException $e) {
    echo "Authentication failed: {$e->getMessage()}\n";
    echo "Please check your API key.\n";
} catch (BlestaAiException $e) {
    echo "API error: {$e->getMessage()}\n";
    echo "Code: {$e->getCode()}\n";
} catch (Exception $e) {
    echo "Unexpected error: {$e->getMessage()}\n";
}
