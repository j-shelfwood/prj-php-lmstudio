<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObjects;

/**
 * Represents a response format configuration.
 */
class ResponseFormat implements \JsonSerializable
{
    /**
     * @var string The type of response format
     */
    private string $type;

    /**
     * @var JsonSchema The JSON schema for the response
     */
    private JsonSchema $jsonSchema;

    /**
     * Create a new response format.
     *
     * @param  string  $type  The type of response format
     * @param  JsonSchema  $jsonSchema  The JSON schema for the response
     */
    private function __construct(string $type, JsonSchema $jsonSchema)
    {
        $this->type = $type;
        $this->jsonSchema = $jsonSchema;
    }

    /**
     * Create a new JSON schema response format.
     *
     * @param  JsonSchema  $jsonSchema  The JSON schema for the response
     */
    public static function jsonSchema(JsonSchema $jsonSchema): self
    {
        return new self('json_schema', $jsonSchema);
    }

    /**
     * Get the type of response format.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the JSON schema for the response.
     */
    public function getJsonSchema(): JsonSchema
    {
        return $this->jsonSchema;
    }

    /**
     * Convert the response format to an array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'type' => $this->type,
        ];

        if ($this->type === 'json_schema') {
            $jsonSchemaArray = [
                'schema' => $this->jsonSchema->getSchema(),
            ];

            if ($this->jsonSchema->getName() !== null) {
                $jsonSchemaArray['name'] = $this->jsonSchema->getName();
            }

            if ($this->jsonSchema->isStrict() !== null) {
                $jsonSchemaArray['strict'] = $this->jsonSchema->isStrict();
            }

            $result['json_schema'] = $jsonSchemaArray;
        }

        return $result;
    }
}
