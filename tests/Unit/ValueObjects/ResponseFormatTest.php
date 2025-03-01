<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use Shelfwood\LMStudio\ValueObjects\JsonSchema;
use Shelfwood\LMStudio\ValueObjects\ResponseFormat;
use Tests\TestCase;

class ResponseFormatTest extends TestCase
{
    /** @test */
    public function it_can_create_a_json_schema_response_format(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ];

        $jsonSchema = new JsonSchema($schema);
        $responseFormat = ResponseFormat::jsonSchema($jsonSchema);

        $this->assertEquals('json_schema', $responseFormat->getType());
        $this->assertSame($jsonSchema, $responseFormat->getJsonSchema());
    }

    /** @test */
    public function it_can_be_json_serialized(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ];

        $jsonSchema = new JsonSchema($schema);
        $responseFormat = ResponseFormat::jsonSchema($jsonSchema);
        $serialized = $responseFormat->jsonSerialize();

        $this->assertEquals('json_schema', $serialized['type']);
        $this->assertEquals(['schema' => $schema], $serialized['json_schema']);
    }

    /** @test */
    public function it_serializes_json_schema_with_name_and_strict_when_provided(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ];

        $jsonSchema = new JsonSchema($schema, 'user_schema', true);
        $responseFormat = ResponseFormat::jsonSchema($jsonSchema);
        $serialized = $responseFormat->jsonSerialize();

        $this->assertEquals('json_schema', $serialized['type']);
        $this->assertEquals([
            'schema' => $schema,
            'name' => 'user_schema',
            'strict' => true,
        ], $serialized['json_schema']);
    }
}
