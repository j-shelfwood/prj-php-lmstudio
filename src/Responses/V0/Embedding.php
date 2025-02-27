<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Responses\V0;

use Shelfwood\LMStudio\Responses\Common\Usage;

/**
 * Represents an embedding response from the V0 API.
 */
class Embedding
{
    /**
     * @param  string  $object  The object type
     * @param  array<array{object: string, embedding: array<float>, index: int}>  $data  The embedding data
     * @param  string  $model  The model used for the embedding
     * @param  Usage  $usage  The token usage information
     */
    public function __construct(
        public readonly string $object,
        public readonly array $data,
        public readonly string $model,
        public readonly Usage $usage,
    ) {}

    /**
     * Create an Embedding object from an array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            object: $data['object'] ?? 'list',
            data: array_map(
                fn (array $item) => [
                    'object' => $item['object'] ?? 'embedding',
                    'embedding' => $item['embedding'] ?? [],
                    'index' => $item['index'] ?? 0,
                ],
                $data['data'] ?? []
            ),
            model: $data['model'] ?? '',
            usage: Usage::fromArray($data['usage'] ?? []),
        );
    }
}
