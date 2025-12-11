<?php
/**
 * API Endpoint: Chat Completion
 * Supports both streaming and non-streaming modes
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use BlestaAi\Client\BlestaAiClient;
use BlestaAi\Client\Exceptions\AuthenticationException;
use BlestaAi\Client\Exceptions\InsufficientCreditsException;
use BlestaAi\Client\Exceptions\ValidationException;
use BlestaAi\Client\Exceptions\BlestaAiException;

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!$input || !isset($input['message']) || empty(trim($input['message']))) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Message is required'
    ]);
    exit;
}

// Check if configured
if (!isConfigured()) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'API key not configured. Please update config.php'
    ]);
    exit;
}

// Check rate limit
if (!checkRateLimit()) {
    http_response_code(429);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Rate limit exceeded. Please try again later.'
    ]);
    exit;
}

// Validate CSRF token
if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Invalid CSRF token'
    ]);
    exit;
}

// Extract parameters
$message = trim($input['message']);
$model = $input['model'] ?? DEFAULT_MODEL;
$streaming = $input['streaming'] ?? false;
$temperature = $input['temperature'] ?? DEFAULT_TEMPERATURE;
$maxTokens = $input['max_tokens'] ?? DEFAULT_MAX_TOKENS;

// Build messages array
$messages = [];

// Add system message
$messages[] = [
    'role' => 'system',
    'content' => 'You are a helpful AI assistant. Provide clear, concise, and accurate responses.'
];

// Add conversation history (limit to last 10 messages for context)
if (isset($_SESSION['messages']) && is_array($_SESSION['messages'])) {
    $recentMessages = array_slice($_SESSION['messages'], -10);
    $messages = array_merge($messages, $recentMessages);
}

// Add current user message
$messages[] = [
    'role' => 'user',
    'content' => $message
];

// Initialize client
$client = new BlestaAiClient(
    BLESTA_AI_API_KEY,
    BLESTA_AI_BASE_URL,
    BLESTA_AI_TIMEOUT
);

try {
    incrementRateLimit();

    if ($streaming) {
        // Streaming mode - SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering

        $fullResponse = '';

        $client->streamChatCompletion(
            $model,
            $messages,
            function($chunk, $data) use (&$fullResponse) {
                if ($data && isset($data['choices'][0]['delta']['content'])) {
                    $content = $data['choices'][0]['delta']['content'];
                    $fullResponse .= $content;

                    // Send content chunk
                    echo "data: " . json_encode([
                        'type' => 'content',
                        'content' => $content
                    ]) . "\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                // Send usage data
                if ($data && isset($data['usage'])) {
                    echo "data: " . json_encode([
                        'type' => 'usage',
                        'usage' => [
                            'promptTokens' => $data['usage']['prompt_tokens'] ?? 0,
                            'completionTokens' => $data['usage']['completion_tokens'] ?? 0,
                            'totalTokens' => $data['usage']['total_tokens'] ?? 0,
                            'cost' => $data['usage']['cost'] ?? 0,
                            'remainingBalance' => $data['usage']['balance_remaining'] ?? 0
                        ]
                    ]) . "\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            },
            [
                'temperature' => $temperature,
                'max_tokens' => $maxTokens
            ]
        );

        // Store messages in session
        $_SESSION['messages'][] = ['role' => 'user', 'content' => $message];
        $_SESSION['messages'][] = ['role' => 'assistant', 'content' => $fullResponse];

        // Limit session message history
        if (count($_SESSION['messages']) > MAX_MESSAGES_PER_SESSION) {
            $_SESSION['messages'] = array_slice($_SESSION['messages'], -MAX_MESSAGES_PER_SESSION);
        }

        // Send done event
        echo "data: " . json_encode(['type' => 'done']) . "\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();

    } else {
        // Non-streaming mode
        header('Content-Type: application/json');

        $response = $client->chatCompletion(
            $model,
            $messages,
            [
                'temperature' => $temperature,
                'max_tokens' => $maxTokens
            ]
        );

        $assistantMessage = $response->getContent();

        // Store messages in session
        $_SESSION['messages'][] = ['role' => 'user', 'content' => $message];
        $_SESSION['messages'][] = ['role' => 'assistant', 'content' => $assistantMessage];

        // Limit session message history
        if (count($_SESSION['messages']) > MAX_MESSAGES_PER_SESSION) {
            $_SESSION['messages'] = array_slice($_SESSION['messages'], -MAX_MESSAGES_PER_SESSION);
        }

        echo json_encode([
            'success' => true,
            'response' => $assistantMessage,
            'model' => $response->model,
            'usage' => [
                'promptTokens' => $response->usage->promptTokens,
                'completionTokens' => $response->usage->completionTokens,
                'totalTokens' => $response->usage->totalTokens,
                'cost' => $response->usage->cost,
                'remainingBalance' => $response->usage->remainingBalance
            ]
        ]);
    }

} catch (AuthenticationException $e) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Authentication failed: ' . $e->getMessage(),
        'type' => 'authentication'
    ]);
} catch (InsufficientCreditsException $e) {
    http_response_code(402);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Insufficient credits',
        'type' => 'insufficient_credits',
        'required' => $e->required,
        'available' => $e->available
    ]);
} catch (ValidationException $e) {
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Validation error: ' . $e->getMessage(),
        'type' => 'validation',
        'errors' => $e->getErrors()
    ]);
} catch (BlestaAiException $e) {
    http_response_code($e->getCode() ?: 500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'type' => 'api_error'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
        'type' => 'server_error'
    ]);
}
