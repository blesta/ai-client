<?php

/**
 * Example: List Available Models
 *
 * This example demonstrates how to retrieve the list of available
 * AI models with their pricing information.
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
    echo "Fetching available models...\n\n";

    $models = $client->getModels();

    echo "Available Models (" . count($models) . " total):\n";
    echo str_repeat('-', 80) . "\n";

    foreach ($models as $model) {
        echo "\nModel: {$model->id}\n";
        echo "  Name: {$model->name}\n";

        if ($model->description) {
            echo "  Description: {$model->description}\n";
        }

        if ($model->promptPrice !== null) {
            echo "  Pricing:\n";
            echo "    - Prompt tokens: $" . number_format($model->promptPrice, 6) . " per 1K tokens\n";
            echo "    - Completion tokens: $" . number_format($model->completionPrice, 6) . " per 1K tokens\n";
        }

        if ($model->contextLength !== null) {
            echo "  Context length: " . number_format($model->contextLength) . " tokens\n";
        }
    }

    echo "\n" . str_repeat('-', 80) . "\n";

    // Example: Filter models by provider
    echo "\nOpenAI Models:\n";
    $openaiModels = array_filter($models, fn($m) => str_starts_with($m->id, 'openai/'));
    foreach ($openaiModels as $model) {
        echo "  - {$model->id}\n";
    }

    echo "\nAnthropic Models:\n";
    $anthropicModels = array_filter($models, fn($m) => str_starts_with($m->id, 'anthropic/'));
    foreach ($anthropicModels as $model) {
        echo "  - {$model->id}\n";
    }

} catch (BlestaAiException $e) {
    echo "API error: {$e->getMessage()}\n";
    echo "Code: {$e->getCode()}\n";
} catch (Exception $e) {
    echo "Unexpected error: {$e->getMessage()}\n";
}
