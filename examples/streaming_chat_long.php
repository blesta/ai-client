<?php

/**
 * Example: Long-form Streaming Chat Completion
 *
 * This example uses a prompt that generates a longer response,
 * making it easier to see the streaming effect in action.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BlestaAi\Client\BlestaAiClient;
use BlestaAi\Client\Exceptions\BlestaAiException;

// Replace with your actual API key
$apiKey = 'sk_your_api_key_here';

// For development/testing, you can use localhost
// $client = new BlestaAiClient($apiKey, 'http://localhost:3030/api/v1');

// For production
// $client = new BlestaAiClient($apiKey);

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
    echo "Response:\n";
    echo str_repeat('=', 70) . "\n";
    flush();

    $usageData = null;
    $wordCount = 0;

    // Stream the response - using a prompt that generates a longer response
    $client->streamChatCompletion(
        'openai/gpt-4.1-mini',
        [
            ['role' => 'system', 'content' => 'You are a knowledgeable science educator.'],
            ['role' => 'user', 'content' => 'Explain how black holes form and what happens at the event horizon. Please provide a detailed explanation.']
        ],
        function ($chunk, $data) use (&$usageData, &$wordCount) {
            // Extract content from delta (streaming format)
            if ($data && isset($data['choices'][0]['delta']['content'])) {
                $content = $data['choices'][0]['delta']['content'];
                echo $content;

                // Count words for stats
                $wordCount += str_word_count($content);

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
            'temperature' => 0.7,
            'max_tokens' => 500  // Allow for longer response
        ]
    );

    echo "\n" . str_repeat('=', 70) . "\n\n";

    // Display statistics
    echo "Statistics:\n";
    echo "- Words streamed: ~{$wordCount}\n";

    // Display usage information if available
    if ($usageData) {
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
