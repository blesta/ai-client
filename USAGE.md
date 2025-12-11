# Integration Guide for Blesta

This guide explains how to integrate the Blesta AI PHP Client Library into your Blesta installation.

## Installation in Blesta

### Option 1: Via Composer (Recommended)

If your Blesta installation uses Composer, add the library to your dependencies:

```bash
cd /path/to/blesta
composer require blesta/ai-client
```

### Option 2: Manual Installation

1. Copy the `php-client-library/src` directory into your Blesta module or plugin
2. Include the autoloader in your module/plugin bootstrap file
3. Install Guzzle dependency separately

### Option 3: Include in Module

Copy the library directly into your module:

```
components/
  modules/
    my_ai_module/
      vendor/
        blesta-ai-client/
          src/
            BlestaAiClient.php
            Exceptions/
            Models/
```

## Integration Examples

### 1. Basic Module Integration

```php
<?php
namespace Blesta\Modules\MyAiModule;

use BlestaAi\Client\BlestaAiClient;
use BlestaAi\Client\Exceptions\BlestaAiException;

class MyAiModule extends Module
{
    private BlestaAiClient $aiClient;

    public function __construct()
    {
        parent::__construct();

        // Load AI client with API key from module config
        $apiKey = $this->getModuleSetting('ai_api_key');
        $this->aiClient = new BlestaAiClient($apiKey);
    }

    /**
     * Process AI chat request from user
     */
    public function processChatRequest($userMessage, $model = 'openai/gpt-4')
    {
        try {
            $response = $this->aiClient->chatCompletion($model, [
                ['role' => 'user', 'content' => $userMessage]
            ]);

            return [
                'success' => true,
                'content' => $response->getContent(),
                'usage' => [
                    'tokens' => $response->usage->totalTokens,
                    'cost' => $response->usage->cost,
                    'balance' => $response->usage->remainingBalance
                ]
            ];
        } catch (BlestaAiException $e) {
            $this->Input->setErrors(['api' => ['error' => $e->getMessage()]]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get available AI models for selection
     */
    public function getAvailableModels()
    {
        try {
            $models = $this->aiClient->getModels();

            $modelOptions = [];
            foreach ($models as $model) {
                $modelOptions[$model->id] = sprintf(
                    '%s (Prompt: $%s, Completion: $%s per 1K)',
                    $model->name,
                    number_format($model->promptPrice, 6),
                    number_format($model->completionPrice, 6)
                );
            }

            return $modelOptions;
        } catch (BlestaAiException $e) {
            return [];
        }
    }

    /**
     * Check if user has sufficient credits
     */
    public function checkUserBalance()
    {
        try {
            $balance = $this->aiClient->getCredits();
            return [
                'balance' => $balance,
                'sufficient' => $balance >= 0.10 // Minimum threshold
            ];
        } catch (BlestaAiException $e) {
            return ['balance' => 0, 'sufficient' => false];
        }
    }
}
```

### 2. Widget Integration

Create a support chat widget that uses AI:

```php
<?php
namespace Blesta\Widgets\AiChatWidget;

use BlestaAi\Client\BlestaAiClient;

class AiChatWidget extends Widget
{
    public function getContent($user)
    {
        $apiKey = Configure::get('BlestaAi.api_key');
        $client = new BlestaAiClient($apiKey);

        // Get user's question from request
        $question = $this->get['question'] ?? '';

        if (!empty($question)) {
            try {
                $response = $client->chatCompletion('openai/gpt-3.5-turbo', [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful Blesta support assistant.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $question
                    ]
                ], [
                    'temperature' => 0.7,
                    'max_tokens' => 500
                ]);

                $this->set('ai_response', $response->getContent());
                $this->set('cost', $response->usage->cost);
            } catch (\Exception $e) {
                $this->set('error', 'Unable to process your request.');
            }
        }

        return $this->view->fetch();
    }
}
```

### 3. Streaming Chat Interface

For real-time chat experiences:

```php
public function streamChat()
{
    $apiKey = Configure::get('BlestaAi.api_key');
    $client = new BlestaAiClient($apiKey);

    // Set headers for SSE
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    $messages = json_decode($this->post['messages'], true);

    $client->streamChatCompletion(
        'openai/gpt-4',
        $messages,
        function ($chunk, $data) {
            // Forward SSE data to client
            echo $chunk . "\n";
            flush();

            // Handle usage data at the end
            if ($data && isset($data['usage'])) {
                // Log usage to database
                $this->logAiUsage($data['usage']);
            }
        }
    );
}
```

### 4. Admin Configuration Panel

Add configuration options to your module:

```php
public function getAdminAddFields($vars = [])
{
    $fields = new ModuleFields();

    // API Key field
    $api_key = $fields->label('API Key', 'ai_api_key');
    $api_key->attach(
        $fields->fieldText(
            'ai_api_key',
            $vars->ai_api_key ?? '',
            ['id' => 'ai_api_key']
        )
    );
    $fields->setField($api_key);

    // Test connection button
    $test = $fields->label('Test Connection', 'test_connection');
    $test->attach(
        $fields->fieldButton(
            'test_connection',
            'Test API Key',
            ['class' => 'btn btn-primary']
        )
    );
    $fields->setField($test);

    return $fields;
}

public function testConnection($apiKey)
{
    try {
        $client = new BlestaAiClient($apiKey);
        $balance = $client->getCredits();

        return [
            'success' => true,
            'message' => "Connection successful! Balance: $" . number_format($balance, 4)
        ];
    } catch (BlestaAiException $e) {
        return [
            'success' => false,
            'message' => "Connection failed: " . $e->getMessage()
        ];
    }
}
```

## Best Practices

### 1. Error Handling

Always wrap API calls in try-catch blocks:

```php
try {
    $response = $client->chatCompletion($model, $messages);
    // Handle success
} catch (InsufficientCreditsException $e) {
    // Prompt user to add credits
    $this->setMessage('error', 'Insufficient credits. Please add more credits to continue.');
} catch (AuthenticationException $e) {
    // API key issue - notify admin
    Log::error('AI API authentication failed: ' . $e->getMessage());
} catch (BlestaAiException $e) {
    // General API error
    $this->setMessage('error', 'AI service temporarily unavailable.');
}
```

### 2. Caching Model Lists

Cache the model list to reduce API calls:

```php
public function getCachedModels()
{
    $cacheKey = 'blesta_ai_models';
    $models = Cache::get($cacheKey);

    if ($models === null) {
        $client = new BlestaAiClient($this->getApiKey());
        $models = $client->getModels();
        Cache::put($cacheKey, $models, 3600); // Cache for 1 hour
    }

    return $models;
}
```

### 3. Logging Usage

Track AI usage for billing and analytics:

```php
private function logAiUsage($response)
{
    $this->Record->insert('ai_usage_logs', [
        'user_id' => $this->Session->read('blesta_id'),
        'model' => $response->model,
        'tokens' => $response->usage->totalTokens,
        'cost' => $response->usage->cost,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
```

### 4. Rate Limiting

Implement rate limiting to prevent abuse:

```php
public function checkRateLimit($userId)
{
    $key = "ai_requests_{$userId}";
    $requests = Cache::get($key, 0);

    if ($requests >= 100) { // 100 requests per hour
        throw new Exception('Rate limit exceeded. Please try again later.');
    }

    Cache::put($key, $requests + 1, 3600);
}
```

## Configuration in config.php (NOTE THIS WILL BE STORED IN THE DATABASE)
### See settings-blestaai.html for admin example view

Add AI configuration to your Blesta config:

```php
// AI API Configuration
Configure::set('BlestaAi.api_key', 'your-api-key-here');
Configure::set('BlestaAi.api_url', 'https://ai.blesta.com/api/v1');
Configure::set('BlestaAi.default_model', 'openai/gpt-3.5-turbo');
Configure::set('BlestaAi.max_tokens', 1000);
Configure::set('BlestaAi.temperature', 0.7);
```

## Security Considerations

1. **Never expose API keys**: Store API keys securely in the database or config files
2. **Validate user input**: Always sanitize and validate messages before sending to AI
3. **Implement rate limiting**: Prevent abuse and unexpected costs
4. **Log all requests**: Track usage for auditing and billing
5. **Handle errors gracefully**: Never expose internal error details to end users

## Support

For questions or issues with the library:
- Check the main README.md for API documentation
- Review the examples/ directory for usage patterns
- Contact Blesta support for integration assistance
