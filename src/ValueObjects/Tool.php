<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObjects;

use Shelfwood\LMStudio\Enums\ToolType;
use Shelfwood\LMStudio\Exceptions\InvalidToolDefinitionException;

/**
 * Represents a tool that can be used by the model.
 *
 * A tool is a function that can be called by the model during a conversation.
 * It has a type (currently only 'function' is supported) and a function definition.
 *
 * Example usage:
 * ```php
 * $tool = Tool::function('get_weather', 'Get the weather for a location', [
 *     'location' => ['type' => 'string', 'description' => 'The location to get weather for'],
 * ]);
 * ```
 */
class Tool implements \JsonSerializable
{
    /**
     * Create a new tool.
     *
     * @param  ToolType  $type  The type of the tool
     * @param  ToolFunction  $function  The function definition
     *
     * @throws InvalidToolDefinitionException If the tool definition is invalid
     */
    public function __construct(
        public readonly ToolType $type,
        public readonly ToolFunction $function,
    ) {
        if (empty($function->name)) {
            throw new InvalidToolDefinitionException('Tool function name cannot be empty');
        }

        if (empty($function->description)) {
            throw new InvalidToolDefinitionException('Tool function description cannot be empty');
        }
    }

    /**
     * Create a function tool.
     *
     * @param  string  $name  The name of the function
     * @param  string  $description  The description of the function
     * @param  array<string, mixed>  $parameters  The parameters of the function (JSON Schema)
     * @return self A new function tool
     *
     * @throws InvalidToolDefinitionException If the tool definition is invalid
     */
    public static function function(string $name, string $description, array $parameters = []): self
    {
        if (empty($name)) {
            throw new InvalidToolDefinitionException('Tool function name cannot be empty');
        }

        if (empty($description)) {
            throw new InvalidToolDefinitionException('Tool function description cannot be empty');
        }

        return new self(
            type: ToolType::FUNCTION,
            function: new ToolFunction($name, $description, $parameters),
        );
    }

    /**
     * Convert the tool to an array for JSON serialization.
     *
     * @return array<string, mixed> The JSON-serializable array
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'function' => $this->function->jsonSerialize(),
        ];
    }
}
