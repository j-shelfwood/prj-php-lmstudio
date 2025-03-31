<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Response;

use Shelfwood\LMStudio\Responses\V1\Embedding;

/**
 * Represents an embedding response.
 */
class EmbeddingResponse
{
    /**
     * @param  string  $object  The object type
     * @param  array  $data  The embedding data
     * @param  string  $model  The model used for the embedding
     * @param  array  $usage  The token usage information
     */
    public function __construct(
        public readonly string $object,
        /** @var list<Embedding> */
        public readonly array $data,
        public readonly string $model,
        /** @var array<string, int> */
        public readonly array $usage,
    ) {}

    /**
     * Create an EmbeddingResponse object from an array.
     *
     * @param  array  $data  The response data
     * @return self The created object
     */
    public static function fromArray(array $data): self
    {
        return new self(
            object: $data['object'] ?? 'list',
            data: array_map(fn ($e) => Embedding::fromArray($e), $data['data'] ?? []),
            model: $data['model'] ?? '',
            usage: $data['usage'] ?? [],
        );
    }
}
