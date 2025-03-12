<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Model;

use Shelfwood\LMStudio\Enum\ToolType;
use Shelfwood\LMStudio\Exception\ValidationException;

class Tool
{
    private ToolType $type;
    private array $function;

    /**
     * @param ToolType $type The type of the tool
     * @param array $function The function definition
     */
    public function __construct(ToolType $type, array $function)
    {
        $this->type = $type;
        $this->function = $function;

        $this->validate();
    }

    /**
     * Create a Tool from an array.
     *
     * @param array $data The tool data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $type = ToolType::from($data['type']);
        $function = $data['function'] ?? [];

        return new self($type, $function);
    }

    /**
     * Convert the tool to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'function' => $this->function,
        ];
    }

    /**
     * Get the type of the tool.
     *
     * @return ToolType
     */
    public function getType(): ToolType
    {
        return $this->type;
    }

    /**
     * Get the function definition.
     *
     * @return array
     */
    public function getFunction(): array
    {
        return $this->function;
    }

    /**
     * Validate the tool.
     *
     * @throws ValidationException If the tool is invalid
     */
    private function validate(): void
    {
        if (empty($this->function)) {
            throw new ValidationException('Function definition is required');
        }

        if (!isset($this->function['name'])) {
            throw new ValidationException('Function name is required');
        }

        if (!isset($this->function['parameters'])) {
            throw new ValidationException('Function parameters are required');
        }
    }
}