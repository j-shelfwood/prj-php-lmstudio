<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model;

use Shelfwood\LMStudio\Api\Enum\ResponseFormatType;
use Shelfwood\LMStudio\Api\Exception\ValidationException;

/**
 * Represents a response format in a chat completion request.
 */
class ResponseFormat
{
    private ResponseFormatType $type;

    private ?array $jsonSchema;

    /**
     * @param  ResponseFormatType  $type  The type of the response format
     * @param  array|null  $jsonSchema  The JSON schema definition (required when type is json_schema)
     */
    public function __construct(ResponseFormatType $type, ?array $jsonSchema = null)
    {
        $this->type = $type;
        $this->jsonSchema = $jsonSchema;

        $this->validate();
    }

    /**
     * Create a ResponseFormat from an array.
     *
     * @param  array  $data  The response format data
     */
    public static function fromArray(array $data): self
    {
        $type = ResponseFormatType::from($data['type'] ?? ResponseFormatType::TEXT->value);
        $jsonSchema = $data['json_schema'] ?? null;

        return new self($type, $jsonSchema);
    }

    /**
     * Convert the response format to an array.
     */
    public function toArray(): array
    {
        $data = [
            'type' => $this->type->value,
        ];

        if ($this->jsonSchema !== null) {
            $data['json_schema'] = $this->jsonSchema;
        }

        return $data;
    }

    /**
     * Get the type of the response format.
     */
    public function getType(): ResponseFormatType
    {
        return $this->type;
    }

    /**
     * Get the JSON schema definition.
     */
    public function getJsonSchema(): ?array
    {
        return $this->jsonSchema;
    }

    /**
     * Validate the response format.
     *
     * @throws ValidationException If the response format is invalid
     */
    private function validate(): void
    {
        if ($this->type === ResponseFormatType::JSON_SCHEMA && $this->jsonSchema === null) {
            throw new ValidationException('JSON schema is required when type is json_schema');
        }
    }
}
