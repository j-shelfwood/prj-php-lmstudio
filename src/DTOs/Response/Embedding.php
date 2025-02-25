<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Response;

use JsonSerializable;
use Shelfwood\LMStudio\DTOs\Response\Common\Usage;

final readonly class Embedding implements JsonSerializable
{
    /**
     * @param  array<array<string, mixed>>  $data
     */
    public function __construct(
        public string $object,
        public array $data,
        public string $model,
        public Usage $usage,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            object: $data['object'],
            data: array_map(
                fn (array $embedding) => [
                    'object' => $embedding['object'],
                    'embedding' => $embedding['embedding'],
                    'index' => $embedding['index'],
                ],
                $data['data']
            ),
            model: $data['model'],
            usage: Usage::fromArray($data['usage']),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'object' => $this->object,
            'data' => $this->data,
            'model' => $this->model,
            'usage' => $this->usage->jsonSerialize(),
        ];
    }
}
