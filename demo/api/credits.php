<?php
/**
 * API Endpoint: Get Credit Balance
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

    // Get credits
    $balance = $client->getCredits();
    incrementRateLimit();

    echo json_encode([
        'success' => true,
        'balance' => $balance,
        'formatted' => '$' . number_format($balance, 4)
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
