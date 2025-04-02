<?php

declare(strict_types=1);

namespace Shelfwood\Lmstudio\Api\Model\Tool;

use InvalidArgumentException;

class ToolParameters
{
    private string $type = 'object';

    /**
     * @param  array<string, ToolParameter>  $properties
     * @param  string[]  $required
     */
    public function __construct(
        /** @var array<string, ToolParameter> */
        private array $properties = [],
        /** @var string[] */
        private array $required = []
    ) {}

    public function addProperty(string $name, ToolParameter $parameter): self
    {
        $this->properties[$name] = $parameter;

        return $this;
    }

    public function addRequired(string $name): self
    {
        if (! isset($this->properties[$name])) {
            throw new InvalidArgumentException("Cannot mark non-existent property '{$name}' as required.");
        }

        if (! in_array($name, $this->required, true)) {
            $this->required[] = $name;
        }

        return $this;
    }

    /**
     * Create ToolParameters from an array definition.
     *
     * @param  array  $data  The array definition (e.g., from JSON config)
     *
     * @throws \InvalidArgumentException If the array structure is invalid.
     */
    public static function fromArray(array $data): self
    {
        if (($data['type'] ?? 'object') !== 'object') {
            throw new InvalidArgumentException('ToolParameters must have type "object"');
        }

        $properties = [];
        $required = $data['required'] ?? [];

        if (! is_array($required)) {
            throw new InvalidArgumentException('"required" field must be an array of strings');
        }

        foreach ($required as $reqName) {
            if (! is_string($reqName)) {
                throw new InvalidArgumentException('"required" field must contain only strings');
            }
        }

        if (isset($data['properties']) && is_array($data['properties'])) {
            foreach ($data['properties'] as $name => $propData) {
                if (! is_array($propData)) {
                    throw new InvalidArgumentException("Invalid data for property '{$name}'");
                }

                // Check if required property exists before creating ToolParameter
                if (in_array($name, $required, true) && ! isset($propData['type'])) {
                    throw new InvalidArgumentException("Required property '{$name}' must have a definition.");
                }

                if (isset($propData['type'])) { // Only add if type is defined
                    $properties[$name] = ToolParameter::fromArray($propData); // Assume Shelfwood\Lmstudio\Api\Model\Tool\ToolParameter
                }
            }
        }

        // Final check: ensure all required properties were actually defined in 'properties'
        foreach ($required as $reqName) {
            if (! isset($properties[$reqName])) {
                throw new InvalidArgumentException("Required property '{$reqName}' was not defined in 'properties'.");
            }
        }

        return new self(properties: $properties, required: $required);
    }

    public function toArray(): array
    {
        $propertiesObject = [];

        foreach ($this->properties as $name => $parameter) {
            $propertiesObject[$name] = $parameter->toArray();
        }

        // Return properties as an object {} for JSON schema compliance
        return [
            'type' => $this->type,
            'properties' => empty($propertiesObject) ? new \stdClass : (object) $propertiesObject,
            'required' => $this->required,
        ];
    }
}
