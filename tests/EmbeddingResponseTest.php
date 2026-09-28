<?php

namespace BlestaAi\Client\Tests;

use BlestaAi\Client\Exceptions\BlestaAiException;
use BlestaAi\Client\Models\EmbeddingResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EmbeddingResponseTest extends TestCase
{
    #[Test]
    public function it_orders_vectors_by_index(): void
    {
        $response = EmbeddingResponse::fromArray([
            'model' => 'openai/text-embedding-3-small',
            'dimensions' => 2,
            'data' => [
                ['object' => 'embedding', 'index' => 2, 'embedding' => [0.5, 0.6]],
                ['object' => 'embedding', 'index' => 0, 'embedding' => [0.1, 0.2]],
                ['object' => 'embedding', 'index' => 1, 'embedding' => [0.3, 0.4]],
            ],
            'usage' => ['prompt_tokens' => 3, 'total_tokens' => 3, 'cost' => 0.00000008, 'remaining_balance' => 1.5],
        ]);

        $this->assertSame([0, 1, 2], array_keys($response->getEmbeddings()));
        $this->assertSame([0.5, 0.6], $response->getEmbedding(2));
        $this->assertSame(2, $response->getDimensions());
    }

    #[Test]
    public function it_derives_dimensions_when_absent_and_reads_headers_case_insensitively(): void
    {
        $response = EmbeddingResponse::fromArray([
            'model' => 'm',
            'data' => [['embedding' => [0.1, 0.2, 0.3, 0.4]]],
        ], ['x-request-id' => ['abc-123']]);

        $this->assertSame(4, $response->getDimensions());
        $this->assertSame('abc-123', $response->getRequestId());
        $this->assertSame(0, $response->getUsage()->promptTokens);
    }

    #[Test]
    public function to_array_round_trips(): void
    {
        $data = [
            'object' => 'list',
            'model' => 'openai/text-embedding-3-small',
            'dimensions' => 2,
            'data' => [
                ['object' => 'embedding', 'index' => 0, 'embedding' => [0.1, 0.2]],
            ],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 0,
                'total_tokens' => 5,
                'cost' => 0.0000001,
                'remaining_balance' => 10.0,
            ],
        ];

        $array = EmbeddingResponse::fromArray($data, ['X-Request-Id' => ['r1']])->toArray();

        $this->assertSame($data + ['request_id' => 'r1'], $array);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function malformedBodies(): array
    {
        return [
            'no data' => [['model' => 'm']],
            'data not array' => [['model' => 'm', 'data' => 'nope']],
            'no model' => [['data' => [['index' => 0, 'embedding' => [0.1]]]]],
            'item without embedding' => [['model' => 'm', 'data' => [['index' => 0]]]],
            'non-numeric value' => [['model' => 'm', 'data' => [['index' => 0, 'embedding' => [0.1, 'x']]]]],
            'duplicate index' => [['model' => 'm', 'data' => [
                ['index' => 0, 'embedding' => [0.1]],
                ['index' => 0, 'embedding' => [0.2]],
            ]]],
            'empty data' => [['model' => 'm', 'data' => []]],
            'negative index' => [['model' => 'm', 'data' => [['index' => -1, 'embedding' => [0.1]]]]],
            'string index' => [['model' => 'm', 'data' => [['index' => '0', 'embedding' => [0.1]]]]],
            'float index' => [['model' => 'm', 'data' => [['index' => 0.0, 'embedding' => [0.1]]]]],
            'null index' => [['model' => 'm', 'data' => [['index' => null, 'embedding' => [0.1]]]]],
            'index out of range' => [['model' => 'm', 'data' => [
                ['index' => 0, 'embedding' => [0.1]],
                ['index' => 2, 'embedding' => [0.2]],
            ]]],
            'empty vector' => [['model' => 'm', 'data' => [['index' => 0, 'embedding' => []]]]],
            'inconsistent lengths' => [['model' => 'm', 'data' => [
                ['index' => 0, 'embedding' => [0.1, 0.2]],
                ['index' => 1, 'embedding' => [0.3]],
            ]]],
            'declared dimensions disagree' => [['model' => 'm', 'dimensions' => 3, 'data' => [
                ['index' => 0, 'embedding' => [0.1, 0.2]],
            ]]],
            'declared dimensions not an int' => [['model' => 'm', 'dimensions' => '2', 'data' => [
                ['index' => 0, 'embedding' => [0.1, 0.2]],
            ]]],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('malformedBodies')]
    public function it_rejects_malformed_bodies(array $body): void
    {
        $this->expectException(BlestaAiException::class);

        EmbeddingResponse::fromArray($body);
    }
}
