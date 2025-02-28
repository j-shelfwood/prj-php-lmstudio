<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObjects;

/**
 * Represents a function definition for a tool.
 */
class ToolFunction implements \JsonSerializable
{
    /**
     * @param  string  $name  The name of the function
     * @param  string  $description  The description of the function
     * @param  array  $parameters  The parameters of the function (JSON Schema)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters = [],
    ) {}

    /**
     * Convert the function to an array.
     */
    public function jsonSerialize(): array
    {
        $data = [
            'name' => $this->name,
            'description' => $this->description,
        ];

        if (! empty($this->parameters)) {
            $data['parameters'] = [
                'type' => 'object',
                'properties' => $this->parameters,
                'required' => array_keys(array_filter($this->parameters, fn ($param) => ($param['required'] ?? false))),
            ];
        } else {
            $data['parameters'] = [
                'type' => 'object',
                'properties' => [],
            ];
        }

        return $data;
    }
}
