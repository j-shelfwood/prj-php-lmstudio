<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Response;

use Shelfwood\LMStudio\Api\Model\ModelInfo;

class ModelResponse
{
    private string $object;
    private array $data;

    /**
     * @param string $object The object type
     * @param array $data The model data
     */
    public function __construct(string $object, array $data)
    {
        $this->object = $object;
        $this->data = $data;
    }

    /**
     * Create a ModelResponse from an array.
     *
     * @param array $data The response data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $object = $data['object'] ?? 'list';
        $modelData = $data['data'] ?? [];

        return new self($object, $modelData);
    }

    /**
     * Convert the model response to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'object' => $this->object,
            'data' => $this->data,
        ];
    }

    /**
     * Get the object type.
     *
     * @return string
     */
    public function getObject(): string
    {
        return $this->object;
    }

    /**
     * Get the model data.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the models as ModelInfo objects.
     *
     * @return ModelInfo[]
     */
    public function getModels(): array
    {
        return array_map(function (array $model) {
            return ModelInfo::fromArray($model);
        }, $this->data);
    }
}