<?php

/**
 * Example: Streaming Chat Completion
 *
 * This example demonstrates how to use streaming mode to receive
 * chat completion responses in real-time as they are generated.
 *
 * NOTE: When running in a browser, you may still see the entire response at once
 * if the response is very short or if your web server has additional buffering enabled.
 * For longer responses or when running from the command line, you should see
 * the text appear word-by-word as it's generated.
 *
 * To see true streaming in a browser, try:
 * - Using a longer prompt that generates more text
 * - Reducing max_tokens to slow down generation
 * - Checking your web server configuration (Nginx/Apache buffering settings)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BlestaAi\Client\BlestaAiClient;
use BlestaAi\Client\Exceptions\BlestaAiException;

// Replace with your actual API key
$apiKey = 'sk_your_api_key_here';

// For development/testing, you can use localhost
// $client = new BlestaAiClient($apiKey, 'http://localhost:3030/api/v1');

// For production
$client = new BlestaAiClient($apiKey);

try {
    // Disable output buffering for true streaming
    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    // Set implicit flush for automatic output
    if (function_exists('apache_setenv')) {
        apache_setenv('no-gzip', '1');
    }
    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', 'off');
    ini_set('implicit_flush', '1');

    echo "Starting streaming chat completion...\n\n";
    echo "Response: ";
    flush();

    $usageData = null;

    // Stream the response
    $client->streamChatCompletion(
        'openai/gpt-4',
        [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Tell me a short fun fact about space.']
        ],
        function ($chunk, $data) use (&$usageData) {
            // Extract content from delta (streaming format)
            if ($data && isset($data['choices'][0]['delta']['content'])) {
                echo $data['choices'][0]['delta']['content'];

                // Force output to be sent immediately
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            // Capture usage data from the final chunk
            if ($data && isset($data['usage'])) {
                $usageData = $data['usage'];
            }
        },
        [
            'temperature' => 0.8,
            'max_tokens' => 200
        ]
    );

    echo "\n\n";

    // Display usage information if available
    if ($usageData) {
        echo "Usage Information:\n";
        echo "- Prompt tokens: {$usageData['prompt_tokens']}\n";
        echo "- Completion tokens: {$usageData['completion_tokens']}\n";
        echo "- Total tokens: {$usageData['total_tokens']}\n";
        echo "- Cost: $" . number_format($usageData['cost'] ?? 0, 6) . "\n";
        echo "- Remaining balance: $" . number_format($usageData['remaining_balance'] ?? 0, 4) . "\n";
    }

} catch (BlestaAiException $e) {
    echo "\n\nAPI error: {$e->getMessage()}\n";
    echo "Code: {$e->getCode()}\n";
} catch (Exception $e) {
    echo "\n\nUnexpected error: {$e->getMessage()}\n";
}
