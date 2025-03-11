<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObjects;

use Shelfwood\LMStudio\Enums\ToolType;

/**
 * Represents a tool that can be used by the model.
 */
class Tool implements \JsonSerializable
{
    /**
     * @param  ToolType  $type  The type of the tool
     * @param  ToolFunction  $function  The function definition
     */
    public function __construct(
        public readonly ToolType $type,
        public readonly ToolFunction $function,
    ) {}

    /**
     * Create a function tool.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function function(string $name, string $description, array $parameters = []): self
    {
        return new self(
            type: ToolType::FUNCTION,
            function: new ToolFunction($name, $description, $parameters),
        );
    }

    /**
     * Convert the tool to an array.
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'function' => $this->function->jsonSerialize(),
        ];
    }
}
