<?php

declare(strict_types=1);

namespace Shelfwood\Lmstudio\Api\Model\Tool;

use InvalidArgumentException;

class ToolDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly ToolParameters $parameters
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters->toArray(),
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['name']) || ! is_string($data['name']) || empty($data['name'])) {
            throw new InvalidArgumentException("Tool definition must include a non-empty 'name'.");
        }

        if (! isset($data['description']) || ! is_string($data['description'])) {
            // Allow empty description
            $data['description'] = '';
        }

        if (! isset($data['parameters']) || ! is_array($data['parameters'])) {
            throw new InvalidArgumentException("Tool definition for '{$data['name']}' must include 'parameters' object.");
        }

        // Use ToolParameters::fromArray for robust creation
        $parameters = ToolParameters::fromArray($data['parameters']); // Assume Shelfwood\Lmstudio\Api\Model\Tool\ToolParameters

        return new self(
            $data['name'],
            $data['description'],
            $parameters
        );
    }
}
