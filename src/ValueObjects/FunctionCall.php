<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObjects;

/**
 * Represents a function call in a tool call.
 */
class FunctionCall implements \JsonSerializable
{
    /**
     * @param  string  $name  The name of the function to call
     * @param  string  $arguments  The arguments to pass to the function (JSON string)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $arguments,
    ) {}

    /**
     * Convert the function call to an array.
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }

    /**
     * Get the arguments as an array.
     */
    public function getArgumentsAsArray(): array
    {
        return json_decode($this->arguments, true) ?? [];
    }
}
