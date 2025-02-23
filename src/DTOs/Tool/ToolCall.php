<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Tool;

use JsonSerializable;

final readonly class ToolCall implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $type,
        public ToolFunction $function,
        public ?string $arguments = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            function: ToolFunction::fromArray($data['function']),
            arguments: $data['function']['arguments'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'id' => $this->id,
            'type' => $this->type,
            'function' => array_merge(
                $this->function->jsonSerialize(),
                ['arguments' => $this->arguments],
            ),
        ], fn ($value) => $value !== null);
    }
}
