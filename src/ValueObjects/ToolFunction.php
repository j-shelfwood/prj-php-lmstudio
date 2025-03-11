<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObjects;

use Shelfwood\LMStudio\Exceptions\InvalidToolDefinitionException;

/**
 * Represents a function definition for a tool.
 *
 * A tool function defines the name, description, and parameters of a function
 * that can be called by the model during a conversation.
 *
 * Example usage:
 * ```php
 * $function = new ToolFunction(
 *     'get_weather',
 *     'Get the weather for a location',
 *     [
 *         'location' => [
 *             'type' => 'string',
 *             'description' => 'The location to get weather for',
 *             'required' => true,
 *         ],
 *     ]
 * );
 * ```
 */
class ToolFunction implements \JsonSerializable
{
    /**
     * Create a new tool function.
     *
     * @param  string  $name  The name of the function
     * @param  string  $description  The description of the function
     * @param  array<string, mixed>  $parameters  The parameters of the function (JSON Schema)
     *
     * @throws InvalidToolDefinitionException If the function definition is invalid
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters = [],
    ) {
        if (empty($name)) {
            throw new InvalidToolDefinitionException('Function name cannot be empty');
        }

        if (empty($description)) {
            throw new InvalidToolDefinitionException('Function description cannot be empty');
        }

        // Validate parameters
        foreach ($this->parameters as $paramName => $param) {
            if (empty($paramName)) {
                throw new InvalidToolDefinitionException('Parameter name cannot be empty');
            }

            if (! isset($param['type'])) {
                throw new InvalidToolDefinitionException("Parameter '{$paramName}' must have a type");
            }
        }
    }

    /**
     * Get the required parameters.
     *
     * @return array<string> The names of the required parameters
     */
    public function getRequiredParameters(): array
    {
        return array_keys(array_filter($this->parameters, fn ($param) => ($param['required'] ?? false)));
    }

    /**
     * Convert the function to an array for JSON serialization.
     *
     * @return array<string, mixed> The JSON-serializable array
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
                'required' => $this->getRequiredParameters(),
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
