<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObjects;

/**
 * Represents a tool call in a chat conversation.
 */
class ToolCall implements \JsonSerializable
{
    /**
     * @param  string  $id  The ID of the tool call
     * @param  string  $type  The type of the tool call (e.g., 'function')
     * @param  FunctionCall  $function  The function to call
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly FunctionCall $function,
    ) {}

    /**
     * Create a function tool call.
     */
    public static function function(string $name, string $arguments, ?string $id = null): self
    {
        return new self(
            id: $id ?? uniqid('call_'),
            type: 'function',
            function: new FunctionCall($name, $arguments),
        );
    }

    /**
     * Convert the tool call to an array.
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'function' => $this->function->jsonSerialize(),
        ];
    }
}
