<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model\Tool;

class ToolParameter
{
    public function __construct(
        public readonly string $type,
        public readonly string $description
    ) {}

    /**
     * Create ToolParameter from an array definition.
     *
     * @param  array  $data  The array definition (e.g., ['type' => 'string', 'description' => '...'])
     *
     * @throws \InvalidArgumentException If the array structure is invalid.
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['type']) || ! is_string($data['type'])) {
            throw new \InvalidArgumentException('Tool parameter must have a valid "type" string.');
        }

        if (! isset($data['description']) || ! is_string($data['description'])) {
            throw new \InvalidArgumentException('Tool parameter must have a valid "description" string.');
        }

        return new self(type: $data['type'], description: $data['description']);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'description' => $this->description,
        ];
    }
}
