<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\LMStudio\Response\Common;

use JsonSerializable;

final readonly class ModelInfo implements JsonSerializable
{
    public function __construct(
        public ?string $arch = null,
        public ?string $quant = null,
        public ?string $format = null,
        public ?int $contextLength = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            arch: $data['arch'] ?? null,
            quant: $data['quant'] ?? null,
            format: $data['format'] ?? null,
            contextLength: $data['context_length'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'arch' => $this->arch,
            'quant' => $this->quant,
            'format' => $this->format,
            'context_length' => $this->contextLength,
        ], fn ($value) => $value !== null);
    }
}
