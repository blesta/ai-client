<?php

namespace BlestaAi\Client\Tests;

use BlestaAi\Client\BlestaAiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Builds a BlestaAiClient whose Guzzle client is backed by a MockHandler, and
 * records every request sent through it.
 */
abstract class ClientTestCase extends TestCase
{
    /**
     * @var array<int, array{request: RequestInterface, options: array<string, mixed>}>
     */
    protected array $history = [];

    /**
     * @param array<int, Response|\Throwable> $responses Queued responses
     */
    protected function makeClient(array $responses, string $baseUrl = 'https://ai.blesta.com/api/v1'): BlestaAiClient
    {
        $this->history = [];

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $client = new BlestaAiClient('sk_test_key', $baseUrl);

        // Swap in a Guzzle client with the same defaults as the constructor,
        // but backed by the mock handler.
        $http = new Client([
            'handler' => $stack,
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer sk_test_key',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);

        $property = new \ReflectionProperty(BlestaAiClient::class, 'httpClient');
        $property->setAccessible(true);
        $property->setValue($client, $http);

        return $client;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    protected function json(int $status, array $body, array $headers = []): Response
    {
        return new Response($status, array_merge(['Content-Type' => 'application/json'], $headers), json_encode($body));
    }

    protected function lastRequest(): RequestInterface
    {
        $this->assertNotEmpty($this->history, 'No request was sent');

        return $this->history[count($this->history) - 1]['request'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function lastOptions(): array
    {
        return $this->history[count($this->history) - 1]['options'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function lastRequestBody(): array
    {
        return json_decode((string)$this->lastRequest()->getBody(), true);
    }
}
