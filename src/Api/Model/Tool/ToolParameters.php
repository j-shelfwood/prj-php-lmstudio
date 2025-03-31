<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model\Tool;

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
            throw new \InvalidArgumentException('ToolParameters must have type "object"');
        }

        $properties = [];

        if (isset($data['properties']) && is_array($data['properties'])) {
            foreach ($data['properties'] as $name => $propData) {
                if (! is_array($propData)) {
                    throw new \InvalidArgumentException("Invalid data for property '{$name}'");
                }
                $properties[$name] = ToolParameter::fromArray($propData);
            }
        }

        $required = $data['required'] ?? [];

        if (! is_array($required)) {
            throw new \InvalidArgumentException('"required" field must be an array of strings');
        }

        foreach ($required as $reqName) {
            if (! is_string($reqName)) {
                throw new \InvalidArgumentException('"required" field must contain only strings');
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

        return [
            'type' => $this->type,
            'properties' => (object) $propertiesObject,
            'required' => $this->required,
        ];
    }
}
