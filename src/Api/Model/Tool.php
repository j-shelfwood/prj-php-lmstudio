<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model;

use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Exception\ValidationException;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;

class Tool
{
    private ToolType $type;

    private ToolDefinition $definition;

    /**
     * @param  ToolType  $type  The type of the tool
     * @param  ToolDefinition  $definition  The tool definition
     */
    public function __construct(ToolType $type, ToolDefinition $definition)
    {
        $this->type = $type;
        $this->definition = $definition;

        $this->validate();
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

    /**
     * Get the type of the tool.
     */
    public function getType(): ToolType
    {
        return $this->type;
    }

    /**
     * Get the tool definition.
     */
    public function getDefinition(): ToolDefinition
    {
        return $this->definition;
    }

    /**
     * Get the name of the tool.
     */
    public function getName(): string
    {
        return $this->definition->getName();
    }

    /**
     * Get the description of the tool.
     */
    public function getDescription(): string
    {
        return $this->definition->getDescription();
    }

    /**
     * Get the parameters of the tool.
     */
    public function getParameters(): object
    {
        return $this->definition->getParameters();
    }

    /**
     * Validate the tool.
     *
     * @throws ValidationException If the tool is invalid
     */
    private function validate(): void
    {
        // The validation is now handled by the ToolDefinition class
        // We only need to validate that we have a definition
        if ($this->definition === null) {
            throw new ValidationException('Tool definition is required');
        }
    }
}
