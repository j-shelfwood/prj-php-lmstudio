<?php

declare(strict_types=1);

namespace Shelfwood\Lmstudio\Api\Model\Tool;

use InvalidArgumentException;

class ToolParameter
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $description = null
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
        if (! isset($data['type']) || ! is_string($data['type']) || empty($data['type'])) {
            throw new InvalidArgumentException('Tool parameter must have a valid non-empty "type" string.');
        }

        $description = (isset($data['description']) && is_string($data['description'])) ? $data['description'] : null;

        return new self(type: $data['type'], description: $description);
    }

    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return $result;
    }
}
