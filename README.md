# Blesta AI PHP Client Library

A modern PHP 8.1+ client library for interacting with the Blesta AI API (ai.blesta.com). This library provides a simple, intuitive interface for chat completions, streaming responses, model listings, and credit balance management.

## Features

- **Modern PHP 8.1+**: Uses typed properties, readonly classes, and named arguments
- **Streaming Support**: Real-time Server-Sent Events (SSE) streaming for chat completions
- **Rate Limit Monitoring**: Automatic extraction and exposure of rate limit information
- **Comprehensive Error Handling**: Specific exception types for different error scenarios (including rate limits)
- **PSR-4 Compliant**: Follows PHP-FIG standards with proper autoloading
- **Well Documented**: Extensive PHPDoc comments and usage examples
- **Guzzle HTTP Client**: Robust HTTP handling with built-in retry logic

## Requirements

- PHP 8.1 or higher
- Composer
- Guzzle HTTP client (^7.0)

## Installation

Install via Composer:

```bash
composer require blesta/ai-client
```

Or add to your `composer.json`:

```json
{
    "require": {
        "blesta/ai-client": "^1.0"
    }
}
```

## Quick Start

```php
<?php

require_once 'vendor/autoload.php';

use BlestaAi\Client\BlestaAiClient;

// Initialize the client with your API key
$client = new BlestaAiClient('your-api-key');

// Send a chat completion request
$response = $client->chatCompletion('openai/gpt-4', [
    ['role' => 'user', 'content' => 'Hello!']
]);

echo $response->getContent();
echo "Cost: $" . $response->usage->cost;
echo "Balance: $" . $response->usage->remainingBalance;

// Check rate limit status
if ($response->rateLimit) {
    echo "Rate limit: {$response->rateLimit->remaining}/{$response->rateLimit->limit}";
}
```

## Usage Examples

### Non-Streaming Chat Completion

```php
use BlestaAi\Client\BlestaAiClient;

$client = new BlestaAiClient('your-api-key');

$response = $client->chatCompletion('openai/gpt-4', [
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
    ['role' => 'user', 'content' => 'What is 2+2?']
], [
    'temperature' => 0.7,
    'max_tokens' => 100
]);

// Access response content
echo $response->getContent();

// Access usage information
echo "Model: {$response->model}\n";
echo "Tokens used: {$response->usage->totalTokens}\n";
echo "Cost: $" . number_format($response->usage->cost, 6) . "\n";
echo "Remaining balance: $" . number_format($response->usage->remainingBalance, 4) . "\n";
```

### Streaming Chat Completion

Stream responses in real-time:

```php
$client->streamChatCompletion(
    'openai/gpt-4',
    [
        ['role' => 'user', 'content' => 'Tell me a story']
    ],
    function ($chunk, $data) {
        // Print content as it arrives
        if ($data && isset($data['choices'][0]['delta']['content'])) {
            echo $data['choices'][0]['delta']['content'];
            flush();
        }

        // Usage data is in the final chunk
        if ($data && isset($data['usage'])) {
            echo "\nCost: $" . $data['usage']['cost'];
            echo "\nBalance: $" . $data['usage']['balance_remaining'];
        }
    },
    [
        'temperature' => 0.8,
        'max_tokens' => 500
    ]
);
```

### List Available Models

```php
$models = $client->getModels();

foreach ($models as $model) {
    echo "{$model->id}\n";
    echo "  Prompt price: $" . $model->promptPrice . " per 1K tokens\n";
    echo "  Completion price: $" . $model->completionPrice . " per 1K tokens\n";
}
```

### Check Credit Balance

```php
$balance = $client->getCredits();
echo "Current balance: $" . number_format($balance, 4);
```

## Configuration

### Custom Base URL

For development or custom deployments:

```php
$client = new BlestaAiClient(
    apiKey: 'your-api-key',
    baseUrl: 'http://localhost:3030/api/v1'
);
```

### Custom Timeout

Set a custom timeout (in seconds):

```php
$client = new BlestaAiClient(
    apiKey: 'your-api-key',
    baseUrl: 'https://ai.blesta.com/api/v1',
    timeout: 60  // 60 seconds
);
```

## Exception Handling

The library provides specific exception types for different error scenarios:

```php
use BlestaAi\Client\Exceptions\AuthenticationException;
use BlestaAi\Client\Exceptions\InsufficientCreditsException;
use BlestaAi\Client\Exceptions\RateLimitException;
use BlestaAi\Client\Exceptions\ValidationException;
use BlestaAi\Client\Exceptions\BlestaAiException;

try {
    $response = $client->chatCompletion('openai/gpt-4', [
        ['role' => 'user', 'content' => 'Hello!']
    ]);
} catch (AuthenticationException $e) {
    // Invalid or missing API key (401)
    echo "Authentication failed: {$e->getMessage()}";
} catch (InsufficientCreditsException $e) {
    // Not enough credits (402)
    echo "Insufficient credits!";
    echo "Required: $" . $e->required;
    echo "Available: $" . $e->available;
} catch (RateLimitException $e) {
    // Rate limit exceeded (429)
    echo "Rate limit exceeded!";
    echo "Retry after: {$e->getRetryAfter()} seconds";
    echo "Reset at: " . date('Y-m-d H:i:s', $e->getResetAt());
} catch (ValidationException $e) {
    // Invalid request parameters (422)
    echo "Validation errors: " . json_encode($e->getErrors());
} catch (BlestaAiException $e) {
    // General API error
    echo "API error: {$e->getMessage()} (Code: {$e->getCode()})";
}
```

## Rate Limiting

The Blesta AI API implements rate limiting to ensure fair usage. The client library automatically extracts and exposes rate limit information from API responses.

### Monitoring Rate Limits

Every successful API response includes rate limit information (when rate limiting is enabled):

```php
$response = $client->chatCompletion('openai/gpt-4', [
    ['role' => 'user', 'content' => 'Hello!']
]);

// Check if rate limiting is enabled
if ($response->rateLimit !== null) {
    echo "Limit: {$response->rateLimit->limit} requests\n";
    echo "Remaining: {$response->rateLimit->remaining} requests\n";
    echo "Resets in: {$response->rateLimit->getSecondsUntilReset()} seconds\n";

    // Check if approaching limit
    if ($response->rateLimit->isNearLimit(0.2)) {
        echo "⚠️ WARNING: Approaching rate limit (below 20%)!\n";
    }
}
```

### Handling Rate Limit Errors

When you exceed the rate limit, a `RateLimitException` is thrown:

```php
use BlestaAi\Client\Exceptions\RateLimitException;

try {
    $response = $client->chatCompletion('openai/gpt-4', [
        ['role' => 'user', 'content' => 'Hello!']
    ]);
} catch (RateLimitException $e) {
    // Rate limit exceeded - wait and retry
    $retryAfter = $e->getRetryAfter();

    echo "Rate limit exceeded!\n";
    echo "Waiting {$retryAfter} seconds before retrying...\n";

    sleep($retryAfter + 1);  // Wait + 1 second buffer

    // Retry the request...
}
```

### Rate Limit Properties

The `RateLimit` object provides:

- **`limit`** (int): Maximum requests allowed per window
- **`remaining`** (int): Requests remaining in current window
- **`reset`** (int): Unix timestamp when the limit resets
- **`getSecondsUntilReset()`**: Seconds until reset
- **`isNearLimit(float $threshold)`**: Check if approaching limit (e.g., 0.1 = 10% remaining)

### Best Practices

1. **Check Rate Limits Proactively**: Monitor `remaining` requests and implement backoff before hitting the limit
2. **Handle RateLimitException**: Always catch and handle rate limit errors gracefully
3. **Respect Retry-After**: Use the `retryAfter` value from the exception, don't guess
4. **Implement Exponential Backoff**: For repeated failures, increase wait time exponentially
5. **Use Light Endpoints**: Check rate limits with lightweight endpoints like `getModels()` or `getCredits()`

See `examples/rate_limit_test.php` for a complete example.

## API Reference

### BlestaAiClient

#### Constructor

```php
public function __construct(
    string $apiKey,
    string $baseUrl = 'https://ai.blesta.com/api/v1',
    int $timeout = 30
)
```

#### Methods

##### chatCompletion()

Send a non-streaming chat completion request.

```php
public function chatCompletion(
    string $model,
    array $messages,
    array $options = []
): ChatCompletion
```

**Parameters:**
- `$model` - Model identifier (e.g., "openai/gpt-4", "anthropic/claude-3-sonnet")
- `$messages` - Array of message objects with 'role' and 'content'
- `$options` - Optional parameters:
  - `temperature` (float): 0-2, default varies by model
  - `max_tokens` (int): Maximum tokens to generate
  - `top_p` (float): 0-1, nucleus sampling
  - `frequency_penalty` (float): -2 to 2
  - `presence_penalty` (float): -2 to 2

**Returns:** `ChatCompletion` object

**Throws:** `AuthenticationException`, `InsufficientCreditsException`, `ValidationException`, `BlestaAiException`

##### streamChatCompletion()

Send a streaming chat completion request.

```php
public function streamChatCompletion(
    string $model,
    array $messages,
    callable $callback,
    array $options = []
): void
```

**Parameters:**
- `$model` - Model identifier
- `$messages` - Array of message objects
- `$callback` - Function called for each chunk: `function(string $chunk, ?array $data): void`
- `$options` - Same as `chatCompletion()`

##### getModels()

Get list of available models with pricing.

```php
public function getModels(): array
```

**Returns:** Array of `Model` objects

##### getCredits()

Get current credit balance.

```php
public function getCredits(): float
```

**Returns:** Current balance as a float

### Response Models

#### ChatCompletion

```php
readonly class ChatCompletion
{
    public string $id;
    public string $model;
    public array $choices;
    public Usage $usage;
    public int $created;

    public function getContent(): string;
    public function getFinishReason(): string;
    public function toArray(): array;
}
```

#### Usage

```php
readonly class Usage
{
    public int $promptTokens;
    public int $completionTokens;
    public int $totalTokens;
    public float $cost;
    public float $remainingBalance;

    public function toArray(): array;
}
```

#### Model

```php
readonly class Model
{
    public string $id;
    public string $name;
    public ?string $description;
    public ?float $promptPrice;
    public ?float $completionPrice;
    public ?int $contextLength;

    public function toArray(): array;
}
```

## Examples

The `examples/` directory contains complete, runnable examples:

- **`chat_completion.php`** - Basic non-streaming chat completion
- **`streaming_chat.php`** - Real-time streaming response
- **`list_models.php`** - List all available models with pricing
- **`check_credits.php`** - Check your credit balance

To run an example:

```bash
cd examples
php chat_completion.php
```

**Note:** Update the `$apiKey` variable in each example with your actual API key.

## Supported Models

The API supports models from various providers:

- **OpenAI**: `openai/gpt-4`, `openai/gpt-3.5-turbo`, etc.
- **Anthropic**: `anthropic/claude-3-opus`, `anthropic/claude-3-sonnet`, etc.
- **X.AI**: `x-ai/grok-4-fast`, etc.
- **Google**: `google/gemini-pro`, etc.
- And many more...

Use `getModels()` to retrieve the current list of available models with pricing.

## Development

### Project Structure

```
php-client-library/
├── src/
│   ├── BlestaAiClient.php          # Main client class
│   ├── Exceptions/
│   │   ├── BlestaAiException.php   # Base exception
│   │   ├── AuthenticationException.php
│   │   ├── InsufficientCreditsException.php
│   │   └── ValidationException.php
│   └── Models/
│       ├── ChatCompletion.php
│       ├── Model.php
│       └── Usage.php
├── examples/                        # Usage examples
├── composer.json                    # Dependencies
└── README.md                        # This file
```

### Installing Dependencies

```bash
cd php-client-library
composer install
```

## Use in Blesta

This library is designed to be used within Blesta modules or plugins to integrate AI functionality. Here's a basic example:

```php
class MyBlestaModule extends Module
{
    public function processAiRequest($apiKey, $userMessage)
    {
        $client = new \BlestaAi\Client\BlestaAiClient($apiKey);

        try {
            $response = $client->chatCompletion('openai/gpt-4', [
                ['role' => 'user', 'content' => $userMessage]
            ]);

            return [
                'success' => true,
                'content' => $response->getContent(),
                'cost' => $response->usage->cost,
                'balance' => $response->usage->remainingBalance
            ];
        } catch (\BlestaAi\Client\Exceptions\BlestaAiException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
```

## Database Schema for Blesta Integration

When integrating AI functionality into Blesta, you'll need to store chat conversations and messages for context management. This schema follows Blesta's database conventions and supports multi-tenant architecture.

### Overview

The schema uses two main tables:
- **`ai_conversations`** - Stores conversation sessions/threads
- **`ai_messages`** - Stores individual messages (system prompts, user messages, assistant responses)

Additional settings like remaining tokens, temperature, and max_tokens can be stored in Blesta's existing settings table.

### Table: ai_conversations

Stores conversation sessions with immutable (append-only) design following Blesta's log table pattern.

```sql
CREATE TABLE `ai_conversations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL COMMENT 'Multi-tenant support (normally 1)',
  `staff_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Staff member ID (0 = system)',
  `title` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Conversation title/description',
  `model` varchar(64) COLLATE utf8_unicode_ci NOT NULL COMMENT 'AI model used (e.g., openai/gpt-4)',
  `status` enum('active','archived') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'active',
  `date_created` datetime NOT NULL COMMENT 'When conversation was created',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `staff_id` (`staff_id`),
  KEY `status` (`status`),
  KEY `date_created` (`date_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
```

**Field Descriptions:**

- **`id`**: Auto-increment primary key
- **`company_id`**: Links to Blesta's company table for multi-tenant support (typically 1)
- **`staff_id`**: Links to Blesta's staff table (0 = system-generated, >0 = staff member)
- **`title`**: Optional human-readable title for the conversation
- **`model`**: The AI model identifier used for this conversation
- **`status`**: Active conversations vs. archived ones (no physical deletion for audit trail)
- **`date_created`**: Timestamp when conversation was started

### Table: ai_messages

Stores individual messages within conversations, including system prompts, user input, and AI responses.

```sql
CREATE TABLE `ai_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` int(10) unsigned NOT NULL COMMENT 'Parent conversation',
  `role` enum('system','user','assistant') COLLATE utf8_unicode_ci NOT NULL COMMENT 'Message role',
  `content` mediumtext COLLATE utf8_unicode_ci NOT NULL COMMENT 'Message content',
  `prompt_tokens` int(10) unsigned DEFAULT NULL COMMENT 'Tokens in prompt (for assistant messages)',
  `completion_tokens` int(10) unsigned DEFAULT NULL COMMENT 'Tokens in completion (for assistant messages)',
  `cost` decimal(10, 4) DEFAULT NULL COMMENT 'Credit cost for this API call (for assistant messages)',
  `date_created` datetime NOT NULL COMMENT 'When message was created',
  PRIMARY KEY (`id`),
  KEY `conversation_id` (`conversation_id`),
  KEY `role` (`role`),
  KEY `date_created` (`date_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
```

**Field Descriptions:**

- **`id`**: Auto-increment primary key
- **`conversation_id`**: Foreign key to `ai_conversations.id`
- **`role`**: Message type:
  - `system` - System prompts that set AI behavior
  - `user` - Messages from users/staff
  - `assistant` - Responses from the AI
- **`content`**: The actual message text (uses `mediumtext` to support long responses)
- **`prompt_tokens`**: Number of tokens in the prompt (populated for assistant responses)
- **`completion_tokens`**: Number of tokens in the completion (populated for assistant responses)
- **`cost`**: Credit cost for this API call (populated for assistant responses)
- **`date_created`**: Timestamp when message was added

### Design Rationale

**Immutable/Append-Only Design:**
- Messages are never edited or deleted, only marked as archived
- Maintains complete audit trail of all AI interactions
- Follows Blesta's `log_*` table pattern for compliance

**Message Roles:**
- **system**: Contains instructions/prompts that guide AI behavior (e.g., "You are a helpful Blesta support assistant")
- **user**: Input from staff members or system
- **assistant**: Responses from the AI model

**Credit/Cost Tracking:**
- Tokens and credit costs are tracked per-message for granular reporting
- Only assistant messages have token/cost data
- Enables per-conversation and per-staff usage analysis

**Multi-Tenancy:**
- `company_id` supports multiple Blesta installations in same database
- Standard Blesta pattern for enterprise deployments

### Usage Examples

#### Creating a New Conversation with System Prompt

```php
// Create conversation
$conversationId = $this->Record->insert('ai_conversations', [
    'company_id' => 1,
    'staff_id' => $staffId, // or 0 for system
    'title' => 'Customer Support Chat',
    'model' => 'openai/gpt-4',
    'status' => 'active',
    'date_created' => date('Y-m-d H:i:s')
]);

// Add system prompt
$this->Record->insert('ai_messages', [
    'conversation_id' => $conversationId,
    'role' => 'system',
    'content' => 'You are a helpful Blesta support assistant. Provide clear, accurate answers about Blesta features.',
    'date_created' => date('Y-m-d H:i:s')
]);
```

#### Adding a User Message and Getting AI Response

```php
// Add user message to conversation
$this->Record->insert('ai_messages', [
    'conversation_id' => $conversationId,
    'role' => 'user',
    'content' => $userQuestion,
    'date_created' => date('Y-m-d H:i:s')
]);

// Retrieve conversation context (all messages for this conversation)
$messages = $this->Record->select(['role', 'content'])
    ->from('ai_messages')
    ->where('conversation_id', '=', $conversationId)
    ->order(['id' => 'ASC'])
    ->fetchAll();

// Send to AI API
$client = new \BlestaAi\Client\BlestaAiClient($apiKey);
$response = $client->chatCompletion('openai/gpt-4', $messages);

// Save AI response
$this->Record->insert('ai_messages', [
    'conversation_id' => $conversationId,
    'role' => 'assistant',
    'content' => $response->getContent(),
    'prompt_tokens' => $response->usage->promptTokens,
    'completion_tokens' => $response->usage->completionTokens,
    'cost' => $response->usage->cost,
    'date_created' => date('Y-m-d H:i:s')
]);
```

#### Retrieving Conversation History

```php
// Get all conversations for a staff member
$conversations = $this->Record->select()
    ->from('ai_conversations')
    ->where('company_id', '=', $companyId)
    ->where('staff_id', '=', $staffId)
    ->where('status', '=', 'active')
    ->order(['date_created' => 'DESC'])
    ->fetchAll();

// Get all messages in a conversation
$messages = $this->Record->select()
    ->from('ai_messages')
    ->where('conversation_id', '=', $conversationId)
    ->order(['id' => 'ASC'])
    ->fetchAll();
```

#### Calculating Total Usage and Credits

```php
// Total credits used by a staff member
$totalCredits = $this->Record->select(['SUM(m.cost) as total_credits'])
    ->from('ai_messages', 'm')
    ->innerJoin('ai_conversations', 'c', 'c.id', '=', 'm.conversation_id', false)
    ->where('c.staff_id', '=', $staffId)
    ->where('c.company_id', '=', $companyId)
    ->fetch();

// Total tokens used by company
$totalTokens = $this->Record->select([
        'SUM(m.prompt_tokens) as total_prompt_tokens',
        'SUM(m.completion_tokens) as total_completion_tokens'
    ])
    ->from('ai_messages', 'm')
    ->innerJoin('ai_conversations', 'c', 'c.id', '=', 'm.conversation_id', false)
    ->where('c.company_id', '=', $companyId)
    ->fetch();
```

#### Archiving Old Conversations

```php
// Archive conversations older than 90 days
$this->Record->where('company_id', '=', $companyId)
    ->where('date_created', '<', date('Y-m-d H:i:s', strtotime('-90 days')))
    ->update('ai_conversations', ['status' => 'archived']);
```

### Storing Settings

Use Blesta's existing settings table for AI-related configuration:

```php
// Store API key per company
Settings::setSetting('ai_api_key', $apiKey, $companyId);

// Store default model
Settings::setSetting('ai_default_model', 'openai/gpt-4', $companyId);

// Store default parameters
Settings::setSetting('ai_temperature', '0.7', $companyId);
Settings::setSetting('ai_max_tokens', '500', $companyId);

// Store remaining balance cache (updated after each API call)
Settings::setSetting('ai_balance', $balance, $companyId);
```

### Migration Script

When implementing this schema, create a Blesta migration file:

```php
// /path/to/blesta/components/upgrades/tasks/upgrade_x_x_x.php
class UpgradeXxx extends UpgradeUtil
{
    public function __construct(Record $Record)
    {
        parent::__construct($Record);
    }

    public function up()
    {
        // Create ai_conversations table
        if (!$this->Record->tableExists('ai_conversations')) {
            $this->Record->query("
                CREATE TABLE `ai_conversations` (
                  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `company_id` int(10) unsigned NOT NULL,
                  `staff_id` int(10) unsigned NOT NULL DEFAULT 0,
                  `title` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `model` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
                  `status` enum('active','archived') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'active',
                  `date_created` datetime NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `company_id` (`company_id`),
                  KEY `staff_id` (`staff_id`),
                  KEY `status` (`status`),
                  KEY `date_created` (`date_created`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
            ");
        }

        // Create ai_messages table
        if (!$this->Record->tableExists('ai_messages')) {
            $this->Record->query("
                CREATE TABLE `ai_messages` (
                  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `conversation_id` int(10) unsigned NOT NULL,
                  `role` enum('system','user','assistant') COLLATE utf8_unicode_ci NOT NULL,
                  `content` mediumtext COLLATE utf8_unicode_ci NOT NULL,
                  `prompt_tokens` int(10) unsigned DEFAULT NULL,
                  `completion_tokens` int(10) unsigned DEFAULT NULL,
                  `cost` decimal(10, 4) DEFAULT NULL COMMENT 'Credit cost',
                  `date_created` datetime NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `conversation_id` (`conversation_id`),
                  KEY `role` (`role`),
                  KEY `date_created` (`date_created`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
            ");
        }
    }

    public function down()
    {
        // Drop tables in reverse order
        $this->Record->query("DROP TABLE IF EXISTS `ai_messages`");
        $this->Record->query("DROP TABLE IF EXISTS `ai_conversations`");
    }
}
```

### Best Practices

1. **Context Management**: Load only necessary messages to stay within token limits
   - Consider limiting context to last N messages or last X tokens
   - Summarize old messages if conversations get very long

2. **Credit Control**: Monitor usage per staff member and set limits
   - Implement daily/monthly credit usage caps
   - Alert when approaching limits

3. **Privacy**: Ensure sensitive data is handled appropriately
   - Consider encryption for content field if storing PII
   - Implement proper access controls

4. **Performance**: Add indexes for common queries
   - Index on `company_id`, `staff_id`, `conversation_id`
   - Consider archiving old conversations

5. **Error Handling**: Store failed requests for debugging
   - Consider adding an error log table
   - Track API errors separately from successful messages

## License

MIT License

## Support

For issues, questions, or contributions, please visit:
- **API Documentation**: https://ai.blesta.com/docs/api (in development mode)
- **Blesta Support**: https://www.blesta.com/support/

## Changelog

### Version 1.0.0 (Initial Release)

- Chat completions (streaming and non-streaming)
- Model listing with pricing
- Credit balance checking
- Comprehensive exception handling
- PSR-4 autoloading
- Full documentation and examples
