<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model\Tool;

class ToolParameter
{
    public function __construct(
        public readonly string $type,
        public readonly string $description
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'description' => $this->description,
        ];
    }
}
