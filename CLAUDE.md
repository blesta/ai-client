# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP 8.1+ client library for the Blesta AI API (ai.blesta.com), providing chat completion, streaming, model listing, and credit management functionality. The library is designed for integration into Blesta modules/plugins but can be used independently.

## Key Commands

### Development Setup
```bash
# Install dependencies
composer install

# Run examples (requires API key)
php examples/chat_completion.php
php examples/streaming_chat.php
php examples/list_models.php
php examples/check_credits.php
```

### Testing
- No automated test suite currently exists
- Examples in `examples/` directory serve as manual integration tests
- Each example requires updating the `$apiKey` variable with a valid API key

## Architecture

### Core Components

**BlestaAiClient** (`src/BlestaAiClient.php`)
- Main entry point for all API interactions
- Uses Guzzle HTTP client for requests
- Constructor parameters: `$apiKey`, `$baseUrl` (default: https://ai.blesta.com/api/v1), `$timeout` (default: 30s)
- Key methods:
  - `chatCompletion()` - Non-streaming chat requests, returns `ChatCompletion` object
  - `streamChatCompletion()` - Streaming requests via SSE, calls callback for each chunk
  - `getModels()` - Returns array of `Model` objects with pricing
  - `getCredits()` - Returns float balance

**Response Models** (`src/Models/`)
- All models are `readonly` classes (immutable)
- `ChatCompletion`: Contains `id`, `model`, `choices[]`, `usage`, `created`
  - Helper methods: `getContent()`, `getFinishReason()`, `toArray()`
- `Usage`: Token counts (`promptTokens`, `completionTokens`, `totalTokens`), `cost`, `remainingBalance`
- `Model`: Model metadata with `id`, `name`, `description`, `promptPrice`, `completionPrice`, `contextLength`

**Exceptions** (`src/Exceptions/`)
- `BlestaAiException` - Base exception for all API errors
- `AuthenticationException` - 401 errors (invalid/missing API key)
- `InsufficientCreditsException` - 402 errors, has `required` and `available` properties
- `ValidationException` - 422 errors, has `getErrors()` method returning validation messages array

### Exception Handling Pattern

All API methods throw specific exceptions based on HTTP status codes:
- 401 → `AuthenticationException`
- 402 → `InsufficientCreditsException`
- 422 → `ValidationException`
- Others → `BlestaAiException`

Always wrap API calls in try-catch blocks and handle each exception type appropriately.

### Streaming Implementation

Streaming uses Server-Sent Events (SSE):
- Sets `stream: true` in request payload
- Reads response body line-by-line using `readLine()` private method
- Parses lines starting with `data: ` prefix
- Calls callback with both raw chunk and parsed JSON data
- Terminates on `[DONE]` message
- Usage data appears in the final chunk before `[DONE]`

## Important Patterns

### API Request Structure
```php
$payload = [
    'model' => 'provider/model-name',  // e.g., 'openai/gpt-4'
    'messages' => [
        ['role' => 'system', 'content' => '...'],
        ['role' => 'user', 'content' => '...'],
        ['role' => 'assistant', 'content' => '...']
    ],
    'stream' => false,  // or true for streaming
    // Optional parameters:
    'temperature' => 0.7,
    'max_tokens' => 500,
    'top_p' => 1.0,
    'frequency_penalty' => 0.0,
    'presence_penalty' => 0.0
];
```

### Blesta Integration Context

When integrating into Blesta:
- Store API keys in database using `Settings::setSetting('ai_api_key', $key, $companyId)`
- Use Blesta's `Record` class for database operations
- Follow Blesta's multi-tenant pattern with `company_id` foreign keys
- Use immutable/append-only design for conversation/message logs
- Implement rate limiting to prevent abuse
- Cache model lists (1 hour TTL recommended)

### Database Schema for Blesta

The library expects integrations to maintain:
- `ai_conversations` table: tracks conversation sessions with `company_id`, `staff_id`, `model`, `status`, `date_created`
- `ai_messages` table: stores individual messages with `conversation_id`, `role` (system/user/assistant), `content`, `prompt_tokens`, `completion_tokens`, `cost`, `date_created`

Messages are never deleted, only archived (audit trail requirement).

## Configuration

### Environment-Specific Base URLs
- Production: `https://ai.blesta.com/api/v1` (default)
- Development: `http://localhost:3030/api/v1` (for local API testing)

### Timeout Considerations
- Default: 30 seconds
- Streaming requests may need longer timeouts (60-120s) for complex queries
- Adjust via constructor: `new BlestaAiClient($apiKey, $baseUrl, $timeout)`

## Model Naming Convention

Models follow `provider/model-name` format:
- OpenAI: `openai/gpt-4`, `openai/gpt-3.5-turbo`
- Anthropic: `anthropic/claude-3-opus`, `anthropic/claude-3-sonnet`
- X.AI: `x-ai/grok-4-fast`
- Google: `google/gemini-pro`

Use `getModels()` to fetch current list dynamically.

## Cost Tracking

All operations that consume credits include usage data:
- Non-streaming: Available in `ChatCompletion::$usage`
- Streaming: Sent in final chunk before `[DONE]` with keys:
  - `usage.prompt_tokens`
  - `usage.completion_tokens`
  - `usage.total_tokens`
  - `usage.cost`
  - `usage.balance_remaining`

Log usage data per-message for granular reporting and cost attribution.

## Security Notes

- Never commit API keys to repository
- API keys should be stored encrypted in production databases
- Validate and sanitize all user input before sending to API
- Implement rate limiting per user/session
- Consider token limits to prevent unexpectedly large costs
- Use HTTPS for all production API calls

## PHP Version Requirements

- Minimum: PHP 8.1
- Uses modern PHP features: typed properties, readonly classes, named arguments, promoted constructors
- When adding code, maintain PHP 8.1+ syntax and patterns

## Dependencies

- `guzzlehttp/guzzle: ^7.0` - HTTP client (handles retries, timeouts, streaming)
- PSR-4 autoloading via Composer
- No other external dependencies

## Confidence Checking for Auto-Replies

The `confidence-check.md` file documents the recommended two-step approach for automatic ticket replies:
1. Generate AI response
2. Evaluate confidence with separate API call (using cheaper model like gpt-4o-mini)
3. Auto-send if confidence >= threshold (default 90%), otherwise save as draft

This pattern prevents low-quality automatic responses in production support scenarios.
