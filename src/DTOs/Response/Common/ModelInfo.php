<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Response\Common;

use JsonSerializable;

final readonly class ModelInfo implements JsonSerializable
{
    public function __construct(
        public string $arch,
        public string $quant,
        public string $format,
        public int $contextLength,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            arch: $data['arch'],
            quant: $data['quant'],
            format: $data['format'],
            contextLength: $data['context_length'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'arch' => $this->arch,
            'quant' => $this->quant,
            'format' => $this->format,
            'context_length' => $this->contextLength,
        ];
    }
}
