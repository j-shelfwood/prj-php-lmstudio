<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use InvalidArgumentException;
use Shelfwood\LMStudio\ValueObjects\JsonSchema;
use Tests\TestCase;

class JsonSchemaTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated_with_a_valid_schema(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ];

        $jsonSchema = new JsonSchema($schema);

        $this->assertEquals($schema, $jsonSchema->getSchema());
        $this->assertNull($jsonSchema->getName());
        $this->assertNull($jsonSchema->isStrict());
    }

    /** @test */
    public function it_can_be_instantiated_with_a_name_and_strict_flag(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ];

        $jsonSchema = new JsonSchema($schema, 'user_schema', true);

        $this->assertEquals($schema, $jsonSchema->getSchema());
        $this->assertEquals('user_schema', $jsonSchema->getName());
        $this->assertTrue($jsonSchema->isStrict());
    }

    /** @test */
    public function it_throws_an_exception_when_schema_is_missing_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON schema must contain a "type" property');

        new JsonSchema([
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ]);
    }

    /** @test */
    public function it_can_create_an_object_schema(): void
    {
        $jsonSchema = JsonSchema::object([
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
        ], ['name'], 'person_schema', true);

        $expected = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name'],
        ];

        $this->assertEquals($expected, $jsonSchema->getSchema());
        $this->assertEquals('person_schema', $jsonSchema->getName());
        $this->assertTrue($jsonSchema->isStrict());
    }

    /** @test */
    public function it_can_create_an_array_schema(): void
    {
        $jsonSchema = JsonSchema::array(
            [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ],
            null,
            null,
            'people_schema',
            true
        );

        $expected = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ],
        ];

        $this->assertEquals($expected, $jsonSchema->getSchema());
        $this->assertEquals('people_schema', $jsonSchema->getName());
        $this->assertTrue($jsonSchema->isStrict());
    }

    /** @test */
    public function it_can_create_a_key_value_schema(): void
    {
        $jsonSchema = JsonSchema::keyValue('joke', 'string', 'A funny joke', 'joke_schema', true);

        $expected = [
            'type' => 'object',
            'properties' => [
                'joke' => [
                    'type' => 'string',
                    'description' => 'A funny joke',
                ],
            ],
            'required' => ['joke'],
        ];

        $this->assertEquals($expected, $jsonSchema->getSchema());
        $this->assertEquals('joke_schema', $jsonSchema->getName());
        $this->assertTrue($jsonSchema->isStrict());
    }

    /** @test */
    public function it_can_create_a_list_schema(): void
    {
        $jsonSchema = JsonSchema::list(
            'items',
            'string',
            null,
            null,
            null,
            'items_schema',
            true
        );

        $expected = [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ],
            'required' => ['items'],
        ];

        $this->assertEquals($expected, $jsonSchema->getSchema());
        $this->assertEquals('items_schema', $jsonSchema->getName());
        $this->assertTrue($jsonSchema->isStrict());
    }

    /** @test */
    public function it_can_be_converted_to_array(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ];

        $jsonSchema = new JsonSchema($schema, 'user_schema', true);
        $array = $jsonSchema->toArray();

        $this->assertEquals($schema, $array['schema']);
        $this->assertEquals('user_schema', $array['name']);
        $this->assertTrue($array['strict']);
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
        $serialized = $jsonSchema->jsonSerialize();

        $this->assertEquals($schema, $serialized);
    }
}
