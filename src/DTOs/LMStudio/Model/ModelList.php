<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\LMStudio\Model;

use JsonSerializable;

final readonly class ModelList implements JsonSerializable
{
    /**
     * @param  ModelInfo[]  $data
     */
    public function __construct(
        public string $object,
        public array $data,
    ) {}

    /**
     * Creates a new model list from an array
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            object: $data['object'],
            data: array_map(
                fn (array $model) => ModelInfo::fromArray($model),
                $data['data'] ?? []
            ),
        );
    }

    /**
     * Serializes the model list
     */
    public function jsonSerialize(): array
    {
        return [
            'object' => $this->object,
            'data' => array_map(
                fn (ModelInfo $model) => $model->jsonSerialize(),
                $this->data
            ),
        ];
    }

    /**
     * Returns an array of model IDs
     *
     * @return string[]
     */
    public function getModelIds(): array
    {
        return array_map(
            fn (ModelInfo $model) => $model->id,
            $this->data
        );
    }
}
