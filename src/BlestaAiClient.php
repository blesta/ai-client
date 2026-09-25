<?php

namespace BlestaAi\Client;

use BlestaAi\Client\Exceptions\AuthenticationException;
use BlestaAi\Client\Exceptions\BlestaAiException;
use BlestaAi\Client\Exceptions\InsufficientCreditsException;
use BlestaAi\Client\Exceptions\RateLimitException;
use BlestaAi\Client\Exceptions\ValidationException;
use BlestaAi\Client\Models\ChatCompletion;
use BlestaAi\Client\Models\EmbeddingResponse;
use BlestaAi\Client\Models\Model;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Blesta AI API Client
 *
 * PHP client library for interacting with the Blesta AI API (ai.blesta.com).
 * This client provides methods for chat completions, streaming responses,
 * embeddings, model listings, and credit balance checks.
 *
 * @example
 * ```php
 * $client = new BlestaAiClient('your-api-key');
 *
 * // Non-streaming chat completion
 * $response = $client->chatCompletion('openai/gpt-4', [
 *     ['role' => 'user', 'content' => 'Hello!']
 * ]);
 *
 * echo $response->getContent();
 * ```
 */
class BlestaAiClient
{
    /**
     * Default per-request timeout (seconds) for embeddings() when the caller
     * passes no 'timeout'. The server waits up to 90 s for the upstream.
     */
    public const DEFAULT_EMBEDDINGS_TIMEOUT = 120;

    private Client $httpClient;

    /**
     * Create a new Blesta AI client instance.
     *
     * @param string $apiKey Your API key from ai.blesta.com
     * @param string $baseUrl Base URL for the API (default: https://ai.blesta.com/api/v1)
     * @param int $timeout Request timeout in seconds (default: 30)
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://ai.blesta.com/api/v1',
        int $timeout = 30
    ) {
        // Ensure base_uri ends with trailing slash for proper Guzzle path resolution
        $baseUri = rtrim($this->baseUrl, '/') . '/';

        $this->httpClient = new Client([
            'base_uri' => $baseUri,
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Send a chat completion request (non-streaming).
     *
     * @param string $model Model identifier (e.g., "openai/gpt-4", "anthropic/claude-3-sonnet")
     * @param array<int, array<string, string>> $messages Array of message objects with 'role' and 'content'
     * @param array<string, mixed> $options Optional parameters (temperature, max_tokens, timeout, etc.)
     *                                      The 'timeout' key sets a per-request timeout in seconds (overrides constructor default)
     * @return ChatCompletion
     * @throws AuthenticationException
     * @throws InsufficientCreditsException
     * @throws RateLimitException
     * @throws ValidationException
     * @throws BlestaAiException
     *
     * @example
     * ```php
     * $response = $client->chatCompletion('openai/gpt-4', [
     *     ['role' => 'system', 'content' => 'You are a helpful assistant.'],
     *     ['role' => 'user', 'content' => 'What is 2+2?']
     * ], [
     *     'temperature' => 0.7,
     *     'max_tokens' => 100
     * ]);
     *
     * echo $response->getContent();
     * echo "Cost: $" . $response->usage->cost;
     * echo "Balance: $" . $response->usage->remainingBalance;
     *
     * // Check rate limit status
     * if ($response->rateLimit !== null) {
     *     echo "Rate limit: {$response->rateLimit->remaining}/{$response->rateLimit->limit}\n";
     *     if ($response->rateLimit->isNearLimit(0.2)) {
     *         echo "Warning: Approaching rate limit!\n";
     *     }
     * }
     * ```
     */
    public function chatCompletion(string $model, array $messages, array $options = []): ChatCompletion
    {
        // Extract Guzzle request options (not part of the API payload)
        $requestOptions = [];
        if (isset($options['timeout'])) {
            $requestOptions['timeout'] = $options['timeout'];
            unset($options['timeout']);
        }

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
        ], $options);

        try {
            $response = $this->httpClient->post('chat/completions', array_merge(
                ['json' => $payload],
                $requestOptions
            ));

            $data = json_decode($response->getBody()->getContents(), true);
            $headers = $response->getHeaders();

            return ChatCompletion::fromArray($data, $headers);
        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (GuzzleException $e) {
            throw new BlestaAiException(
                'HTTP request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Send a streaming chat completion request.
     *
     * This method streams the response in real-time via Server-Sent Events (SSE).
     * The callback function is called for each chunk of data received.
     *
     * @param string $model Model identifier
     * @param array<int, array<string, string>> $messages Array of message objects
     * @param callable $callback Function to call for each chunk: function(string $chunk, ?array $data): void
     * @param array<string, mixed> $options Optional parameters (temperature, max_tokens, timeout, etc.)
     *                                      The 'timeout' key sets a per-request timeout in seconds (overrides constructor default)
     * @return void
     * @throws AuthenticationException
     * @throws InsufficientCreditsException
     * @throws RateLimitException
     * @throws ValidationException
     * @throws BlestaAiException
     *
     * @example
     * ```php
     * $client->streamChatCompletion('openai/gpt-4', [
     *     ['role' => 'user', 'content' => 'Tell me a story']
     * ], function($chunk, $data) {
     *     if ($data && isset($data['choices'][0]['delta']['content'])) {
     *         echo $data['choices'][0]['delta']['content'];
     *     }
     *
     *     // Usage data is in the final chunk
     *     if ($data && isset($data['usage'])) {
     *         echo "\nCost: $" . $data['usage']['cost'];
     *         echo "\nBalance: $" . $data['usage']['remaining_balance'];
     *     }
     * });
     * ```
     */
    public function streamChatCompletion(
        string $model,
        array $messages,
        callable $callback,
        array $options = []
    ): void {
        // Extract Guzzle request options (not part of the API payload)
        $requestOptions = [];
        if (isset($options['timeout'])) {
            $requestOptions['timeout'] = $options['timeout'];
            unset($options['timeout']);
        }

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
        ], $options);

        try {
            $response = $this->httpClient->post('chat/completions', array_merge(
                ['json' => $payload, 'stream' => true],
                $requestOptions
            ));

            $body = $response->getBody();

            while (!$body->eof()) {
                $line = $this->readLine($body);

                if (empty($line)) {
                    continue;
                }

                // SSE format: "data: <json>"
                if (str_starts_with($line, 'data: ')) {
                    $jsonData = substr($line, 6);

                    if ($jsonData === '[DONE]') {
                        break;
                    }

                    $data = json_decode($jsonData, true);
                    $callback($line, $data);
                }
            }
        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (GuzzleException $e) {
            throw new BlestaAiException(
                'HTTP request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Create embeddings (vectors) for one or more strings.
     *
     * The model must be a concrete embedding model (see getModels('embedding'));
     * "blesta/*" aliases are rejected by the server. The response always reports
     * the concrete model and the actual vector length: store both with your
     * vectors, since vectors from different models or dimensions are not comparable.
     *
     * The response is checked strictly before it is returned (a
     * BlestaAiException is thrown otherwise): one vector per input (1 for a
     * string input), the response model exactly equal to the requested model,
     * every vector non-empty and of the same length (see
     * EmbeddingResponse::fromArray()), and, when 'dimensions' was passed,
     * getDimensions() equal to the requested dimensions.
     *
     * Timeout: unless you pass 'timeout', this request uses a 120 second
     * timeout (DEFAULT_EMBEDDINGS_TIMEOUT) instead of the constructor's
     * default, because the server waits up to 90 seconds for the upstream
     * provider on large batches.
     *
     * @param string $model Embedding model identifier (e.g., "openai/text-embedding-3-small")
     * @param string|array<int, string> $inputs A string or a list of 1-64 non-empty strings
     * @param array<string, mixed> $options Optional:
     *                                      - 'dimensions' (int) reduced vector length, if the model supports it
     *                                      - 'input_type' (string) "query" or "document"; reserved: accepted by
     *                                        the server but currently ignored (not sent upstream)
     *                                      - 'timeout' (int|float) per-request timeout in seconds (not sent to
     *                                        the API); defaults to 120
     * @return EmbeddingResponse
     * @throws AuthenticationException
     * @throws InsufficientCreditsException
     * @throws RateLimitException
     * @throws ValidationException
     * @throws BlestaAiException
     *
     * @example
     * ```php
     * $result = $client->embeddings('openai/text-embedding-3-small', [
     *     'How do I reset my password?',
     *     'Refund policy for annual plans',
     * ], [
     *     'dimensions' => 512,
     * ]);
     *
     * echo $result->getModel() . ' / ' . $result->getDimensions() . " dims\n";
     * foreach ($result->getEmbeddings() as $index => $vector) {
     *     // store $vector with $result->getModel() and $result->getDimensions()
     * }
     * echo "Cost: $" . $result->getUsage()->cost;
     * ```
     */
    public function embeddings(string $model, string|array $inputs, array $options = []): EmbeddingResponse
    {
        // Extract Guzzle request options (not part of the API payload). The
        // server may wait up to 90 s upstream, so default to a longer timeout
        // than the constructor's.
        $requestOptions = ['timeout' => self::DEFAULT_EMBEDDINGS_TIMEOUT];
        if (isset($options['timeout'])) {
            $requestOptions['timeout'] = $options['timeout'];
        }
        unset($options['timeout']);

        $expectedCount = is_string($inputs) ? 1 : count($inputs);

        $payload = array_merge([
            'model' => $model,
            'input' => $inputs,
        ], $options);

        try {
            $response = $this->httpClient->post('embeddings', array_merge(
                ['json' => $payload],
                $requestOptions
            ));

            $data = json_decode($response->getBody()->getContents(), true);

            if (!is_array($data)) {
                throw new BlestaAiException('Malformed embeddings response: body is not JSON');
            }

            $result = EmbeddingResponse::fromArray($data, $response->getHeaders());

            $received = count($result->getEmbeddings());
            if ($received !== $expectedCount) {
                throw new BlestaAiException(
                    'Malformed embeddings response: expected ' . $expectedCount
                    . ' vectors, received ' . $received
                );
            }

            // The server always echoes the requested concrete model.
            if ($result->getModel() !== $model) {
                throw new BlestaAiException(
                    'Malformed embeddings response: requested model "' . $model
                    . '" but the response is for "' . $result->getModel() . '"'
                );
            }

            // A reduced length was requested: vectors of any other length
            // (e.g. the model's native size) must never reach the caller's index.
            if (isset($options['dimensions'])
                && $result->getDimensions() !== (int)$options['dimensions']) {
                throw new BlestaAiException(
                    'Malformed embeddings response: requested ' . (int)$options['dimensions']
                    . ' dimensions but received ' . $result->getDimensions()
                );
            }

            return $result;
        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (GuzzleException $e) {
            throw new BlestaAiException(
                'HTTP request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get list of available models with pricing.
     *
     * By default the server lists chat models (and Blesta model aliases) only.
     * Pass a type to filter: 'embedding' for embedding models, 'all' for
     * everything, or 'chat' for chat models.
     *
     * @param string|null $type Optional model type filter ('chat', 'embedding' or 'all')
     * @return array<int, Model>
     * @throws RateLimitException
     * @throws ValidationException If the type is not recognized by the server
     * @throws BlestaAiException
     *
     * @example
     * ```php
     * $models = $client->getModels();
     *
     * foreach ($models as $model) {
     *     echo $model->id . ": ";
     *     echo "Prompt: $" . $model->promptPrice . ", ";
     *     echo "Completion: $" . $model->completionPrice . "\n";
     * }
     *
     * // Embedding models, with dimensions and the recommended flag
     * foreach ($client->getModels('embedding') as $model) {
     *     echo $model->id . ' (' . $model->dimensions . ' dims)'
     *         . ($model->recommended ? ' [recommended]' : '') . "\n";
     * }
     * ```
     */
    public function getModels(?string $type = null): array
    {
        try {
            $response = $type === null
                ? $this->httpClient->get('models')
                : $this->httpClient->get('models', ['query' => ['type' => $type]]);
            $data = json_decode($response->getBody()->getContents(), true);

            return array_map(
                fn($modelData) => Model::fromArray($modelData),
                $data['data'] ?? []
            );
        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (GuzzleException $e) {
            throw new BlestaAiException(
                'HTTP request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get current credit balance.
     *
     * @return float
     * @throws AuthenticationException
     * @throws RateLimitException
     * @throws BlestaAiException
     *
     * @example
     * ```php
     * $balance = $client->getCredits();
     * echo "Current balance: $" . $balance;
     * ```
     */
    public function getCredits(): float
    {
        try {
            $response = $this->httpClient->get('auth/key');
            $data = json_decode($response->getBody()->getContents(), true);

            return (float)($data['data']['total_credits'] ?? 0.0);
        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (GuzzleException $e) {
            throw new BlestaAiException(
                'HTTP request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Handle Guzzle client exceptions and convert to appropriate exception types.
     *
     * @param ClientException $e
     * @return never
     * @throws AuthenticationException
     * @throws InsufficientCreditsException
     * @throws RateLimitException
     * @throws ValidationException
     * @throws BlestaAiException
     */
    private function handleClientException(ClientException $e): never
    {
        $response = $e->getResponse();
        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true);
        $headers = $response->getHeaders();

        $message = $body['message'] ?? $body['error'] ?? 'API request failed';

        switch ($statusCode) {
            case 401:
                throw new AuthenticationException($message, $statusCode, $e);

            case 402:
                throw new InsufficientCreditsException(
                    required: (float)($body['estimated_cost'] ?? 0.0),
                    available: (float)($body['current_balance'] ?? 0.0),
                    message: $message,
                    code: $statusCode
                );

            case 422:
                throw new ValidationException(
                    errors: $body['messages'] ?? [],
                    message: $message,
                    code: $statusCode
                );

            case 429:
                $limit = (int)($headers['X-RateLimit-Limit'][0] ?? 0);
                $retryAfter = (int)($body['retry_after'] ?? $headers['Retry-After'][0] ?? 60);
                $reset = (int)($headers['X-RateLimit-Reset'][0] ?? time() + $retryAfter);

                throw new RateLimitException(
                    limit: $limit,
                    retryAfter: $retryAfter,
                    resetAt: $reset,
                    message: $message,
                    code: $statusCode
                );

            default:
                throw new BlestaAiException($message, $statusCode, $e);
        }
    }

    /**
     * Read a line from a stream (for SSE parsing).
     *
     * @param \Psr\Http\Message\StreamInterface $stream
     * @return string
     */
    private function readLine($stream): string
    {
        $buffer = '';

        while (!$stream->eof()) {
            $char = $stream->read(1);

            if ($char === "\n") {
                break;
            }

            $buffer .= $char;
        }

        return trim($buffer);
    }
}
