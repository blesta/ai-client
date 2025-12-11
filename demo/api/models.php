<?php
/**
 * API Endpoint: Get Available Models
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use BlestaAi\Client\BlestaAiClient;
use BlestaAi\Client\Exceptions\BlestaAiException;

header('Content-Type: application/json');

try {
    // Check if configured
    if (!isConfigured()) {
        throw new Exception('API key not configured. Please update config.php');
    }

    // Check rate limit
    if (!checkRateLimit()) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded. Please try again later.'
        ]);
        exit;
    }

    // Initialize client
    $client = new BlestaAiClient(
        BLESTA_AI_API_KEY,
        BLESTA_AI_BASE_URL,
        BLESTA_AI_TIMEOUT
    );

    // Get models
    $models = $client->getModels();
    incrementRateLimit();

    // Format models for frontend
    $formattedModels = array_map(function($model) {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'description' => $model->description,
            'promptPrice' => $model->promptPrice,
            'completionPrice' => $model->completionPrice,
            'contextLength' => $model->contextLength,
            'displayName' => sprintf(
                '%s (Prompt: $%s, Completion: $%s per 1K)',
                $model->name,
                number_format($model->promptPrice ?? 0, 6),
                number_format($model->completionPrice ?? 0, 6)
            )
        ];
    }, $models);

    // Sort by name
    usort($formattedModels, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    echo json_encode([
        'success' => true,
        'models' => $formattedModels,
        'count' => count($formattedModels)
    ]);

} catch (BlestaAiException $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
