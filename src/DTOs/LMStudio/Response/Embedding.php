<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\LMStudio\Response;

use JsonSerializable;
use Shelfwood\LMStudio\DTOs\Common\Response\Usage;

final readonly class Embedding implements JsonSerializable
{
    /**
     * @param  array<array<float>>  $embeddings
     */
    public function __construct(
        public string $object,
        public array $embeddings,
        public Usage $usage,
        public string $model,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            object: $data['object'],
            embeddings: $data['embeddings'],
            usage: Usage::fromArray($data['usage']),
            model: $data['model'],
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'object' => $this->object,
            'embeddings' => $this->embeddings,
            'usage' => $this->usage->jsonSerialize(),
            'model' => $this->model,
        ], fn ($value) => $value !== null);
    }
}
