<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Response\Common;

use JsonSerializable;

final readonly class ToolCall implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $type,
        public ToolFunction $function,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            function: ToolFunction::fromArray($data['function']),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'function' => $this->function->jsonSerialize(),
        ];
    }
}
