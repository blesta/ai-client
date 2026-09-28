<?php

namespace BlestaAi\Client\Tests;

use BlestaAi\Client\Exceptions\AuthenticationException;
use BlestaAi\Client\Exceptions\BlestaAiException;
use BlestaAi\Client\Exceptions\InsufficientCreditsException;
use BlestaAi\Client\Exceptions\RateLimitException;
use BlestaAi\Client\Exceptions\ValidationException;
use BlestaAi\Client\Models\EmbeddingResponse;
use BlestaAi\Client\Models\Usage;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;

class EmbeddingsTest extends ClientTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function successBody(): array
    {
        return [
            'object' => 'list',
            'model' => 'openai/text-embedding-3-small',
            'dimensions' => 3,
            'data' => [
                ['object' => 'embedding', 'index' => 0, 'embedding' => [0.1, -0.2, 0.3]],
                ['object' => 'embedding', 'index' => 1, 'embedding' => [0.4, 0.5, -0.6]],
            ],
            'usage' => [
                'prompt_tokens' => 12,
                'total_tokens' => 12,
                'cost' => 0.0000003,
                'remaining_balance' => 42.5,
            ],
        ];
    }

    #[Test]
    public function it_posts_to_the_relative_embeddings_path_and_parses_the_response(): void
    {
        $client = $this->makeClient([
            $this->json(200, $this->successBody(), ['X-Request-Id' => 'req-abc']),
        ]);

        $result = $client->embeddings('openai/text-embedding-3-small', ['first', 'second']);

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://ai.blesta.com/api/v1/embeddings', (string)$request->getUri());
        $this->assertSame('Bearer sk_test_key', $request->getHeaderLine('Authorization'));
        $this->assertSame([
            'model' => 'openai/text-embedding-3-small',
            'input' => ['first', 'second'],
        ], $this->lastRequestBody());

        $this->assertInstanceOf(EmbeddingResponse::class, $result);
        $this->assertSame('openai/text-embedding-3-small', $result->getModel());
        $this->assertSame(3, $result->getDimensions());
        $this->assertSame([0 => [0.1, -0.2, 0.3], 1 => [0.4, 0.5, -0.6]], $result->getEmbeddings());
        $this->assertSame([0.4, 0.5, -0.6], $result->getEmbedding(1));
        $this->assertNull($result->getEmbedding(2));
        $this->assertSame('req-abc', $result->getRequestId());
        $this->assertSame('req-abc', $result->requestId);

        $usage = $result->getUsage();
        $this->assertInstanceOf(Usage::class, $usage);
        $this->assertSame(12, $usage->promptTokens);
        $this->assertSame(0, $usage->completionTokens);
        $this->assertSame(12, $usage->totalTokens);
        $this->assertSame(0.0000003, $usage->cost);
        $this->assertSame(42.5, $usage->remainingBalance);
    }

    #[Test]
    public function it_sends_a_single_string_and_options_and_strips_timeout(): void
    {
        $body = $this->successBody();
        $body['data'] = [['object' => 'embedding', 'index' => 0, 'embedding' => [1, 0, 0]]];

        $client = $this->makeClient([$this->json(200, $body)]);

        $result = $client->embeddings('openai/text-embedding-3-small', 'just one', [
            'dimensions' => 3,
            'input_type' => 'query',
            'timeout' => 120,
        ]);

        $this->assertSame([
            'model' => 'openai/text-embedding-3-small',
            'input' => 'just one',
            'dimensions' => 3,
            'input_type' => 'query',
        ], $this->lastRequestBody());
        $this->assertSame(120, $this->lastOptions()['timeout']);

        // Integers in vectors are normalized to floats.
        $this->assertSame([1.0, 0.0, 0.0], $result->getEmbedding(0));
        $this->assertNull($result->getRequestId());
    }

    #[Test]
    public function it_defaults_the_request_timeout_to_120_seconds(): void
    {
        $client = $this->makeClient([$this->json(200, $this->successBody())]);

        $client->embeddings('openai/text-embedding-3-small', ['first', 'second']);

        $this->assertSame(120, $this->lastOptions()['timeout']);
        $this->assertSame(120, \BlestaAi\Client\BlestaAiClient::DEFAULT_EMBEDDINGS_TIMEOUT);
        $this->assertArrayNotHasKey('timeout', $this->lastRequestBody());
    }

    #[Test]
    public function a_vector_count_that_differs_from_the_input_count_throws(): void
    {
        $client = $this->makeClient([
            // Two vectors for one string input.
            $this->json(200, $this->successBody()),
            // Two vectors for three inputs.
            $this->json(200, $this->successBody()),
        ]);

        foreach (['one string', ['a', 'b', 'c']] as $inputs) {
            try {
                $client->embeddings('openai/text-embedding-3-small', $inputs);
                $this->fail('Expected BlestaAiException');
            } catch (BlestaAiException $e) {
                $this->assertStringContainsString('vectors', $e->getMessage());
            }
        }
    }

    #[Test]
    public function a_response_for_a_different_model_throws(): void
    {
        $body = $this->successBody();
        $body['model'] = 'openai/text-embedding-3-large';

        $client = $this->makeClient([$this->json(200, $body)]);

        $this->expectException(BlestaAiException::class);
        $this->expectExceptionMessage('openai/text-embedding-3-large');

        $client->embeddings('openai/text-embedding-3-small', ['first', 'second']);
    }

    #[Test]
    public function a_vector_length_other_than_the_requested_dimensions_throws(): void
    {
        $vector = array_fill(0, 1536, 0.01);
        $body = $this->successBody();
        $body['dimensions'] = 1536;
        $body['data'] = [['object' => 'embedding', 'index' => 0, 'embedding' => $vector]];

        $client = $this->makeClient([$this->json(200, $body)]);

        $this->expectException(BlestaAiException::class);
        $this->expectExceptionMessage('requested 512 dimensions but received 1536');

        $client->embeddings('openai/text-embedding-3-small', 'one', ['dimensions' => 512]);
    }

    #[Test]
    public function a_vector_length_matching_the_requested_dimensions_passes(): void
    {
        $body = $this->successBody();
        $body['dimensions'] = 512;
        $body['data'] = [['object' => 'embedding', 'index' => 0, 'embedding' => array_fill(0, 512, 0.01)]];

        // Numeric strings are accepted as the requested dimensions too.
        $client = $this->makeClient([$this->json(200, $body), $this->json(200, $body)]);

        $this->assertSame(512, $client->embeddings('openai/text-embedding-3-small', 'one', ['dimensions' => 512])->getDimensions());
        $this->assertSame(512, $client->embeddings('openai/text-embedding-3-small', 'one', ['dimensions' => '512'])->getDimensions());
    }

    #[Test]
    public function base_url_with_trailing_slash_resolves_correctly(): void
    {
        $client = $this->makeClient([$this->json(200, $this->successBody())], 'http://localhost:3030/api/v1/');

        $client->embeddings('openai/text-embedding-3-small', ['a', 'b']);

        $this->assertSame('http://localhost:3030/api/v1/embeddings', (string)$this->lastRequest()->getUri());
    }

    #[Test]
    public function a_malformed_success_body_throws(): void
    {
        $client = $this->makeClient([
            $this->json(200, ['object' => 'list', 'model' => 'x']),
            new Response(200, [], 'not json'),
        ]);

        try {
            $client->embeddings('x', 'a');
            $this->fail('Expected BlestaAiException');
        } catch (BlestaAiException $e) {
            $this->assertStringContainsString('data', $e->getMessage());
        }

        $this->expectException(BlestaAiException::class);
        $client->embeddings('x', 'a');
    }

    #[Test]
    public function a_401_throws_authentication_exception(): void
    {
        $client = $this->makeClient([
            $this->json(401, ['error' => 'Unauthorized - Invalid API key']),
        ]);

        try {
            $client->embeddings('openai/text-embedding-3-small', 'a');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame(401, $e->getCode());
            $this->assertSame('Unauthorized - Invalid API key', $e->getMessage());
        }
    }

    #[Test]
    public function a_402_throws_insufficient_credits_exception(): void
    {
        $client = $this->makeClient([
            $this->json(402, [
                'error' => 'Insufficient balance',
                'message' => 'Your account balance is too low to process this request.',
                'current_balance' => 0.0000001,
                'estimated_cost' => 0.0000005,
            ]),
        ]);

        try {
            $client->embeddings('openai/text-embedding-3-small', 'a');
            $this->fail('Expected InsufficientCreditsException');
        } catch (InsufficientCreditsException $e) {
            $this->assertSame(402, $e->getCode());
            $this->assertSame('Your account balance is too low to process this request.', $e->getMessage());
        }
    }

    #[Test]
    public function a_422_throws_validation_exception_with_messages(): void
    {
        $client = $this->makeClient([
            $this->json(422, [
                'error' => 'Validation failed',
                'messages' => ['model' => ["The model 'x-ai/grok-4-fast' is not an embedding model."]],
            ]),
        ]);

        try {
            $client->embeddings('x-ai/grok-4-fast', 'a');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertSame('Validation failed', $e->getMessage());
            $this->assertArrayHasKey('model', $e->getErrors());
        }
    }

    #[Test]
    public function a_429_throws_rate_limit_exception(): void
    {
        $client = $this->makeClient([
            $this->json(429, [
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => 42,
            ], [
                'X-RateLimit-Limit' => '120',
                'X-RateLimit-Remaining' => '0',
                'Retry-After' => '42',
                'X-RateLimit-Reset' => '1790000000',
            ]),
        ]);

        try {
            $client->embeddings('openai/text-embedding-3-small', 'a');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(429, $e->getCode());
        }
    }

    #[Test]
    public function an_upstream_429_carries_the_forwarded_retry_after(): void
    {
        // Shape the server returns when the upstream provider rate limits.
        $client = $this->makeClient([
            $this->json(429, [
                'error' => 'Upstream API error',
                'message' => 'The request could not be processed.',
                'details' => null,
                'retry_after' => 30,
            ], ['Retry-After' => '30']),
        ]);

        try {
            $client->embeddings('openai/text-embedding-3-small', 'a');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(30, $e->retryAfter);
        }
    }

    #[Test]
    public function a_500_throws_blesta_ai_exception(): void
    {
        $client = $this->makeClient([
            $this->json(500, ['error' => 'Upstream API error', 'message' => 'Failed to process your request.']),
        ]);

        $this->expectException(BlestaAiException::class);
        $client->embeddings('openai/text-embedding-3-small', 'a');
    }
}
