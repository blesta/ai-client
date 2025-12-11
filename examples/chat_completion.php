<?php

/**
 * Example: Non-streaming Chat Completion
 *
 * This example demonstrates how to send a simple chat completion request
 * and receive the full response at once (non-streaming mode).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BlestaAi\Client\BlestaAiClient;
use BlestaAi\Client\Exceptions\AuthenticationException;
use BlestaAi\Client\Exceptions\InsufficientCreditsException;
use BlestaAi\Client\Exceptions\BlestaAiException;

// Replace with your actual API key
$apiKey = 'sk_your_api_key_here';

// For development/testing, you can use localhost
// $client = new BlestaAiClient($apiKey, 'http://localhost:3030/api/v1');

// For production
$client = new BlestaAiClient($apiKey);

try {
    echo "Sending chat completion request...\n\n";

    // Simple conversation
    $response = $client->chatCompletion('openai/gpt-4', [
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        ['role' => 'user', 'content' => 'What is the capital of France?']
    ]);

    // Display the response
    echo "Response:\n";
    echo $response->getContent() . "\n\n";

    // Display usage information
    echo "Usage Information:\n";
    echo "- Model: {$response->model}\n";
    echo "- Prompt tokens: {$response->usage->promptTokens}\n";
    echo "- Completion tokens: {$response->usage->completionTokens}\n";
    echo "- Total tokens: {$response->usage->totalTokens}\n";
    echo "- Cost: $" . number_format($response->usage->cost, 6) . "\n";
    echo "- Remaining balance: $" . number_format($response->usage->remainingBalance, 4) . "\n";
    echo "- Finish reason: {$response->getFinishReason()}\n";

} catch (AuthenticationException $e) {
    echo "Authentication failed: {$e->getMessage()}\n";
    echo "Please check your API key.\n";
} catch (InsufficientCreditsException $e) {
    echo "Insufficient credits: {$e->getMessage()}\n";
    echo "Required: $" . number_format($e->required, 4) . "\n";
    echo "Available: $" . number_format($e->available, 4) . "\n";
} catch (BlestaAiException $e) {
    echo "API error: {$e->getMessage()}\n";
    echo "Code: {$e->getCode()}\n";
} catch (Exception $e) {
    echo "Unexpected error: {$e->getMessage()}\n";
}
