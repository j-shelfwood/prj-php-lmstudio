<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Responses\Common;

/**
 * Represents a tool call in a response.
 */
class ToolCall
{
    /**
     * @param  string  $id  The ID of the tool call
     * @param  string  $type  The type of the tool call (e.g., 'function')
     * @param  Function_  $function  The function to call
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly Function_ $function,
    ) {}

    /**
     * Create a ToolCall object from an array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            type: $data['type'] ?? 'function',
            function: Function_::fromArray($data['function'] ?? []),
        );
    }
}
