<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http\Response\Common;

/**
 * Represents a function in a tool call.
 */
class Function_
{
    /**
     * @param  string  $name  The name of the function
     * @param  string  $arguments  The arguments to the function as a JSON string
     */
    public function __construct(
        public readonly string $name,
        public readonly string $arguments,
    ) {}

    /**
     * Create a Function object from an array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            arguments: $data['arguments'] ?? '{}',
        );
    }

    /**
     * Get the arguments as an associative array.
     */
    public function getArgumentsAsArray(): array
    {
        return json_decode($this->arguments, true) ?? [];
    }
}
