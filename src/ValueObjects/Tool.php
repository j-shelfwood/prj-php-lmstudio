<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObjects;

/**
 * Represents a tool that can be used by the model.
 */
class Tool implements \JsonSerializable
{
    /**
     * @param  string  $type  The type of the tool (e.g., 'function')
     * @param  ToolFunction  $function  The function definition
     */
    public function __construct(
        public readonly string $type,
        public readonly ToolFunction $function,
    ) {}

    /**
     * Create a function tool.
     */
    public static function function(string $name, string $description, array $parameters = []): self
    {
        return new self(
            type: 'function',
            function: new ToolFunction($name, $description, $parameters),
        );
    }

    /**
     * Convert the tool to an array.
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'function' => $this->function->jsonSerialize(),
        ];
    }
}
