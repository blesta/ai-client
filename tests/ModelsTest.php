<?php

namespace BlestaAi\Client\Tests;

use BlestaAi\Client\Exceptions\ValidationException;
use BlestaAi\Client\Models\Model;
use PHPUnit\Framework\Attributes\Test;

class ModelsTest extends ClientTestCase
{
    #[Test]
    public function default_get_models_call_is_unchanged(): void
    {
        $client = $this->makeClient([
            $this->json(200, ['data' => [
                [
                    'id' => 'x-ai/grok-4-fast',
                    'name' => 'x-ai/grok-4-fast',
                    'pricing' => ['prompt' => '0.000125', 'completion' => '0.000625'],
                    'type' => 'chat',
                ],
            ]]),
        ]);

        $models = $client->getModels();

        $request = $this->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://ai.blesta.com/api/v1/models', (string)$request->getUri());
        $this->assertSame('', $request->getUri()->getQuery());

        $this->assertCount(1, $models);
        $this->assertSame('x-ai/grok-4-fast', $models[0]->id);
        $this->assertSame(0.000125, $models[0]->promptPrice);
        $this->assertSame('chat', $models[0]->type);
        $this->assertFalse($models[0]->isEmbedding());
        $this->assertNull($models[0]->dimensions);
    }

    #[Test]
    public function get_models_can_filter_by_type(): void
    {
        $client = $this->makeClient([
            $this->json(200, ['data' => [
                [
                    'id' => 'openai/text-embedding-3-small',
                    'name' => 'openai/text-embedding-3-small',
                    'pricing' => ['prompt' => '0.000025', 'completion' => '0.000000'],
                    'type' => 'embedding',
                    'dimensions' => 1536,
                    'supports_dimensions' => true,
                    'context_length' => 8192,
                    'deprecated_at' => null,
                    'recommended' => true,
                ],
            ]]),
        ]);

        $models = $client->getModels('embedding');

        $this->assertSame('type=embedding', $this->lastRequest()->getUri()->getQuery());
        $this->assertSame('https://ai.blesta.com/api/v1/models?type=embedding', (string)$this->lastRequest()->getUri());

        $model = $models[0];
        $this->assertTrue($model->isEmbedding());
        $this->assertSame(1536, $model->dimensions);
        $this->assertTrue($model->supportsDimensions);
        $this->assertSame(8192, $model->contextLength);
        $this->assertNull($model->deprecatedAt);
        $this->assertFalse($model->isDeprecated());
        $this->assertTrue($model->recommended);
    }

    #[Test]
    public function an_invalid_type_maps_to_validation_exception(): void
    {
        $client = $this->makeClient([
            $this->json(422, ['error' => 'Validation failed', 'messages' => ['type' => ['The selected type is invalid.']]]),
        ]);

        $this->expectException(ValidationException::class);
        $client->getModels('images');
    }

    #[Test]
    public function model_parses_new_fields_when_present(): void
    {
        $model = Model::fromArray([
            'id' => 'baai/bge-m3',
            'name' => 'baai/bge-m3',
            'pricing' => ['prompt' => '0.0000125', 'completion' => '0'],
            'type' => 'embedding',
            'dimensions' => 1024,
            'supports_dimensions' => false,
            'context_length' => 8192,
            'deprecated_at' => '2026-12-01T00:00:00+00:00',
            'recommended' => false,
        ]);

        $this->assertSame('embedding', $model->type);
        $this->assertSame(1024, $model->dimensions);
        $this->assertFalse($model->supportsDimensions);
        $this->assertSame('2026-12-01T00:00:00+00:00', $model->deprecatedAt);
        $this->assertTrue($model->isDeprecated());
        $this->assertFalse($model->recommended);

        $this->assertSame([
            'id' => 'baai/bge-m3',
            'name' => 'baai/bge-m3',
            'description' => null,
            'pricing' => ['prompt' => 0.0000125, 'completion' => 0.0],
            'context_length' => 8192,
            'type' => 'embedding',
            'dimensions' => 1024,
            'supports_dimensions' => false,
            'deprecated_at' => '2026-12-01T00:00:00+00:00',
            'recommended' => false,
        ], $model->toArray());
    }

    #[Test]
    public function model_from_a_1_0_server_payload_leaves_new_fields_null(): void
    {
        $model = Model::fromArray([
            'id' => 'x-ai/grok-4-fast',
            'name' => 'x-ai/grok-4-fast',
            'pricing' => ['prompt' => '0.000125', 'completion' => '0.000625'],
        ]);

        $this->assertNull($model->type);
        $this->assertNull($model->dimensions);
        $this->assertNull($model->supportsDimensions);
        $this->assertNull($model->deprecatedAt);
        $this->assertNull($model->recommended);
        $this->assertFalse($model->isEmbedding());
    }

    #[Test]
    public function positional_constructor_arguments_are_unchanged(): void
    {
        $model = new Model('id', 'name', 'desc', 1.0, 2.0, 4096);

        $this->assertSame('desc', $model->description);
        $this->assertSame(1.0, $model->promptPrice);
        $this->assertSame(2.0, $model->completionPrice);
        $this->assertSame(4096, $model->contextLength);
        $this->assertNull($model->type);
    }
}
