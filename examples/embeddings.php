<?php

/**
 * Example: Embeddings
 *
 * This example demonstrates how to find the recommended embedding model,
 * turn text into vectors, and compare them with cosine similarity.
 *
 * Store the model ID and dimensions alongside every vector you keep:
 * vectors from different models (or different dimensions) are not comparable.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BlestaAi\Client\BlestaAiClient;
use BlestaAi\Client\Exceptions\BlestaAiException;
use BlestaAi\Client\Exceptions\InsufficientCreditsException;
use BlestaAi\Client\Exceptions\RateLimitException;
use BlestaAi\Client\Exceptions\ValidationException;

// Replace with your actual API key
$apiKey = 'sk_your_api_key_here';

// For development/testing, you can use localhost
// $client = new BlestaAiClient($apiKey, 'http://localhost:3030/api/v1');

// For production
$client = new BlestaAiClient($apiKey);

/**
 * Cosine similarity between two equal-length vectors.
 *
 * @param array<int, float> $a
 * @param array<int, float> $b
 */
function cosineSimilarity(array $a, array $b): float
{
    $dot = 0.0;
    $normA = 0.0;
    $normB = 0.0;

    foreach ($a as $i => $value) {
        $dot += $value * $b[$i];
        $normA += $value * $value;
        $normB += $b[$i] * $b[$i];
    }

    return ($normA > 0 && $normB > 0) ? $dot / (sqrt($normA) * sqrt($normB)) : 0.0;
}

try {
    // 1. Find the recommended embedding model (embedding models are only
    //    listed when asked for explicitly).
    $embeddingModels = $client->getModels('embedding');

    $recommended = null;
    foreach ($embeddingModels as $model) {
        echo "Embedding model: {$model->id} ({$model->dimensions} dims"
            . ($model->supportsDimensions ? ', custom dimensions supported' : '')
            . ($model->isDeprecated() ? ", deprecated {$model->deprecatedAt}" : '')
            . ")" . ($model->recommended ? ' [recommended]' : '') . "\n";

        if ($model->recommended) {
            $recommended = $model;
        }
    }

    if ($recommended === null) {
        echo "No recommended embedding model is available.\n";
        exit(1);
    }

    // 2. Embed some documents. Batches of up to 64 strings are allowed.
    $documents = [
        'To reset your password, click "Forgot password" on the login page.',
        'Refunds for annual plans are prorated within the first 30 days.',
        'Our data center is located in Los Angeles, California.',
    ];

    // embeddings() uses a 120 second timeout unless you pass 'timeout'.
    // 'input_type' ("query"|"document") is a reserved optional field: the
    // server accepts it but currently ignores it, so it is not sent here.
    $options = [];
    if ($recommended->supportsDimensions) {
        $options['dimensions'] = 512; // smaller vectors, cheaper to store and search
    }

    $docResult = $client->embeddings($recommended->id, $documents, $options);

    echo "\nEmbedded " . count($docResult->getEmbeddings()) . " documents with "
        . "{$docResult->getModel()} ({$docResult->getDimensions()} dims)\n";
    echo "Cost: $" . number_format($docResult->getUsage()->cost, 8)
        . " | Balance: $" . number_format($docResult->getUsage()->remainingBalance, 4) . "\n";
    echo "Request ID: " . ($docResult->getRequestId() ?? 'n/a') . "\n";

    // 3. Embed a query with the same model and dimensions, then rank.
    $queryOptions = [];
    if (isset($options['dimensions'])) {
        $queryOptions['dimensions'] = $options['dimensions'];
    }

    $query = 'How can I change my password?';
    $queryResult = $client->embeddings($recommended->id, $query, $queryOptions);
    $queryVector = $queryResult->getEmbedding(0);

    $scores = [];
    foreach ($docResult->getEmbeddings() as $index => $vector) {
        $scores[$index] = cosineSimilarity($queryVector, $vector);
    }
    arsort($scores);

    echo "\nQuery: {$query}\n";
    foreach ($scores as $index => $score) {
        echo sprintf("  %.4f  %s\n", $score, $documents[$index]);
    }
} catch (InsufficientCreditsException $e) {
    echo "Insufficient credits: {$e->getMessage()}\n";
} catch (ValidationException $e) {
    echo "Validation error: {$e->getMessage()}\n";
    print_r($e->getErrors());
} catch (RateLimitException $e) {
    echo "Rate limited. Retry in {$e->retryAfter} seconds.\n";
} catch (BlestaAiException $e) {
    echo "API error: {$e->getMessage()}\n";
    echo "Code: {$e->getCode()}\n";
}
