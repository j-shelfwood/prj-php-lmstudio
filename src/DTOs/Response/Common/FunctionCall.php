<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Response\Common;

use JsonSerializable;

final readonly class FunctionCall implements JsonSerializable
{
    public function __construct(
        public string $name,
        public string $arguments,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            arguments: $data['arguments'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }
}
