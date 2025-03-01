<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObjects;

/**
 * Represents a JSON schema for structured output.
 */
class JsonSchema implements \JsonSerializable
{
    /**
     * @var array<string, mixed> The schema definition
     */
    private array $schema;

    /**
     * @var string|null The name of the schema
     */
    private ?string $name;

    /**
     * @var bool|null Whether strict validation is enabled
     */
    private ?bool $strict;

    /**
     * Create a new JSON schema.
     *
     * @param  array<string, mixed>  $schema  The schema definition
     * @param  string|null  $name  Optional name for the schema
     * @param  bool|null  $strict  Whether to enforce strict schema validation
     */
    public function __construct(array $schema, ?string $name = null, ?bool $strict = null)
    {
        $this->validateSchema($schema);
        $this->schema = $schema;
        $this->name = $name;
        $this->strict = $strict;
    }

    /**
     * Create a new JSON schema for an object.
     *
     * @param  array<string, array<string, mixed>>  $properties  The object properties
     * @param  array<string>  $required  The required properties
     * @param  string|null  $name  Optional name for the schema
     * @param  bool|null  $strict  Whether to enforce strict schema validation
     */
    public static function object(array $properties, array $required = [], ?string $name = null, ?bool $strict = null): self
    {
        return new self([
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ], $name, $strict);
    }

    /**
     * Create a new JSON schema for an array.
     *
     * @param  array<string, mixed>  $items  The schema for array items
     * @param  int|null  $minItems  Minimum number of items
     * @param  int|null  $maxItems  Maximum number of items
     * @param  string|null  $name  Optional schema name
     * @param  bool|null  $strict  Whether to enforce strict schema validation
     */
    public static function array(array $items, ?int $minItems = null, ?int $maxItems = null, ?string $name = null, ?bool $strict = null): self
    {
        $schema = [
            'type' => 'array',
            'items' => $items,
        ];

        if ($minItems !== null) {
            $schema['minItems'] = $minItems;
        }

        if ($maxItems !== null) {
            $schema['maxItems'] = $maxItems;
        }

        return new self($schema, $name, $strict);
    }

    /**
     * Create a schema for a simple key-value object.
     *
     * @param  string  $key  The key name
     * @param  string  $type  The value type (string, number, boolean, etc.)
     * @param  string|null  $description  Optional description
     * @param  string|null  $name  Optional schema name
     * @param  bool|null  $strict  Whether to enforce strict validation
     */
    public static function keyValue(string $key, string $type, ?string $description = null, ?string $name = null, ?bool $strict = null): self
    {
        $property = ['type' => $type];

        if ($description !== null) {
            $property['description'] = $description;
        }

        return self::object([
            $key => $property,
        ], [$key], $name, $strict);
    }

    /**
     * Create a schema for a list of items.
     *
     * @param  string  $listName  The name of the list property
     * @param  string  $itemType  The type of items (string, number, object, etc.)
     * @param  array<string, mixed>|null  $itemProperties  If itemType is 'object', the properties of each item
     * @param  array<string>|null  $requiredItemProperties  If itemType is 'object', the required properties of each item
     * @param  int|null  $minItems  Minimum number of items
     * @param  string|null  $name  Optional schema name
     * @param  bool|null  $strict  Whether to enforce strict validation
     */
    public static function list(
        string $listName,
        string $itemType,
        ?array $itemProperties = null,
        ?array $requiredItemProperties = null,
        ?int $minItems = null,
        ?string $name = null,
        ?bool $strict = null
    ): self {
        $items = ['type' => $itemType];

        if ($itemType === 'object' && $itemProperties !== null) {
            $items['properties'] = $itemProperties;

            if ($requiredItemProperties !== null) {
                $items['required'] = $requiredItemProperties;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => [
                $listName => [
                    'type' => 'array',
                    'items' => $items,
                ],
            ],
            'required' => [$listName],
        ];

        if ($minItems !== null) {
            $schema['properties'][$listName]['minItems'] = $minItems;
        }

        return new self($schema, $name, $strict);
    }

    /**
     * Validate the schema structure.
     *
     * @param  array<string, mixed>  $schema  The schema to validate
     *
     * @throws \InvalidArgumentException If the schema is invalid
     */
    private function validateSchema(array $schema): void
    {
        if (! isset($schema['type'])) {
            throw new \InvalidArgumentException('JSON schema must contain a "type" property');
        }
    }

    /**
     * Get the schema definition.
     *
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * Get the schema name.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get the strict validation flag.
     */
    public function isStrict(): ?bool
    {
        return $this->strict;
    }

    /**
     * Convert the schema to an array.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return $this->schema;
    }

    /**
     * Get the full schema representation including metadata.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'schema' => $this->schema,
        ];

        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

        if ($this->strict !== null) {
            $result['strict'] = $this->strict;
        }

        return $result;
    }
}
