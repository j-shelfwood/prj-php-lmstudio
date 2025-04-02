<?php

declare(strict_types=1);

namespace Shelfwood\Lmstudio\Api\Model;

use Shelfwood\Lmstudio\Api\Enum\ToolType;
use Shelfwood\Lmstudio\Api\Model\Tool\ToolDefinition;

class Tool
{
    /**
     * @param  ToolType  $type  The type of the tool
     * @param  ToolDefinition  $definition  The tool definition
     */
    public function __construct(
        public readonly ToolType $type,
        public readonly ToolDefinition $definition
    ) {
        //
    }

    /**
     * Create a Tool from an array.
     *
     * @param  array  $data  The tool data
     */
    public static function fromArray(array $data): self
    {
        $type = ToolType::from($data['type']);
        $function = $data['function'] ?? [];

        return new self($type, ToolDefinition::fromArray($function));
    }

    /**
     * Convert the tool to an array.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'function' => $this->definition->toArray(),
        ];
    }
}
