<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model;

use Shelfwood\LMStudio\Api\Enum\ModelState;
use Shelfwood\LMStudio\Api\Enum\ModelType;

class ModelInfo
{
    /**
     * @param  string  $id  The model ID
     * @param  string  $object  The object type
     * @param  ModelType  $type  The model type
     * @param  string  $publisher  The model publisher
     * @param  string  $arch  The model architecture
     * @param  string  $compatibilityType  The compatibility type
     * @param  string  $quantization  The quantization level
     * @param  ModelState  $state  The model state
     * @param  int  $maxContextLength  The maximum context length
     */
    public function __construct(
        public readonly string $id,
        public readonly string $object,
        public readonly ModelType $type,
        public readonly string $publisher,
        public readonly string $arch,
        public readonly string $compatibilityType,
        public readonly string $quantization,
        public readonly ModelState $state,
        public readonly int $maxContextLength
    ) {}

    /**
     * Create a ModelInfo from an array.
     *
     * @param  array  $data  The model data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['object'],
            ModelType::from($data['type']),
            $data['publisher'],
            $data['arch'],
            $data['compatibility_type'],
            $data['quantization'],
            ModelState::from($data['state']),
            $data['max_context_length']
        );
    }

    /**
     * Convert the model info to an array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'object' => $this->object,
            'type' => $this->type->value,
            'publisher' => $this->publisher,
            'arch' => $this->arch,
            'compatibility_type' => $this->compatibilityType,
            'quantization' => $this->quantization,
            'state' => $this->state->value,
            'max_context_length' => $this->maxContextLength,
        ];
    }

    /**
     * Check if the model is loaded.
     */
    public function isLoaded(): bool
    {
        return $this->state === ModelState::LOADED;
    }
}
