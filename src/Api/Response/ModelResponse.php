<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Response;

use Shelfwood\LMStudio\Api\Model\ModelInfo;

class ModelResponse
{
    /**
     * @param  string  $object  The object type
     * @param  array  $data  The model data
     */
    public function __construct(
        public readonly string $object,
        /** @var list<ModelInfo> */
        public readonly array $data
    ) {
        // Removed duplicate assignments - properties are promoted
    }

    /**
     * Create a ModelResponse from an array.
     *
     * @param  array  $data  The response data
     */
    public static function fromArray(array $data): self
    {
        $object = $data['object'] ?? 'list';
        $modelData = array_map(fn ($m) => ModelInfo::fromArray($m), $data['data'] ?? []);

        return new self($object, $modelData);
    }

    /**
     * Convert the model response to an array.
     */
    public function toArray(): array
    {
        return [
            'object' => $this->object,
            'data' => array_map(fn (ModelInfo $m) => $m->toArray(), $this->data),
        ];
    }

    /**
     * Get the object type.
     */
    public function getObject(): string
    {
        return $this->object;
    }

    /**
     * Get the model data.
     *
     * @return list<ModelInfo>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the models as ModelInfo objects.
     *
     * @return list<ModelInfo>
     */
    public function getModels(): array
    {
        return $this->data;
    }
}
