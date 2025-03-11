<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObjects;

use Shelfwood\LMStudio\Enums\ToolType;
use Shelfwood\LMStudio\Exceptions\InvalidToolDefinitionException;

/**
 * Represents a tool call in a chat conversation.
 *
 * A tool call is a request from the model to execute a specific function
 * with certain arguments. It has an ID, a type, and a function call.
 *
 * Example usage:
 * ```php
 * $toolCall = ToolCall::function(
 *     'get_weather',
 *     json_encode(['location' => 'New York'])
 * );
 * ```
 */
class ToolCall implements \JsonSerializable
{
    /**
     * Create a new tool call.
     *
     * @param  string  $id  The ID of the tool call
     * @param  ToolType  $type  The type of the tool call
     * @param  FunctionCall  $function  The function to call
     *
     * @throws InvalidToolDefinitionException If the tool call is invalid
     */
    public function __construct(
        public readonly string $id,
        public readonly ToolType $type,
        public readonly FunctionCall $function,
    ) {
        if (empty($id)) {
            throw new InvalidToolDefinitionException('Tool call ID cannot be empty');
        }
    }

    /**
     * Create a function tool call.
     *
     * @param  string  $name  The name of the function to call
     * @param  string  $arguments  The arguments to pass to the function (JSON string)
     * @param  string|null  $id  The ID of the tool call (generated if not provided)
     * @return self A new function tool call
     *
     * @throws InvalidToolDefinitionException If the tool call is invalid
     */
    public static function function(string $name, string $arguments, ?string $id = null): self
    {
        if (empty($name)) {
            throw new InvalidToolDefinitionException('Function name cannot be empty');
        }

        // Validate that arguments is a valid JSON string
        if (! empty($arguments)) {
            $decoded = json_decode($arguments, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidToolDefinitionException('Arguments must be a valid JSON string: '.json_last_error_msg());
            }
        }

        return new self(
            id: $id ?? uniqid('call_'),
            type: ToolType::FUNCTION,
            function: new FunctionCall($name, $arguments),
        );
    }

    /**
     * Convert the tool call to an array for JSON serialization.
     *
     * @return array<string, mixed> The JSON-serializable array
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'function' => $this->function->jsonSerialize(),
        ];
    }
}
