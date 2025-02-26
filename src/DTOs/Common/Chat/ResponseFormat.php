<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Common\Chat;

use JsonSerializable;

final readonly class ResponseFormat implements JsonSerializable
{
    /**
     * Create a new response format instance
     *
     * @param  array<string, mixed>  $jsonSchema
     */
    public function __construct(
        public string $type = 'json_schema',
        public array $jsonSchema = []
    ) {}

    /**
     * Create a response format for structured JSON output
     *
     * @param  string  $name  Schema name
     * @param  array<string, mixed>  $schema  The JSON schema definition
     * @param  bool  $strict  Whether to enforce strict schema validation
     */
    public static function jsonSchema(string $name, array $schema, bool $strict = true): self
    {
        return new self(
            type: 'json_schema',
            jsonSchema: [
                'name' => $name,
                'strict' => $strict ? 'true' : 'false',
                'schema' => $schema,
            ]
        );
    }

    /**
     * Convert the response format to a JSON serializable array
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'json_schema' => $this->jsonSchema,
        ];
    }
}
