<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Model;

use Shelfwood\LMStudio\Enum\ModelState;
use Shelfwood\LMStudio\Enum\ModelType;

class ModelInfo
{
    private string $id;
    private string $object;
    private ModelType $type;
    private string $publisher;
    private string $arch;
    private string $compatibilityType;
    private string $quantization;
    private ModelState $state;
    private int $maxContextLength;

    /**
     * @param string $id The model ID
     * @param string $object The object type
     * @param ModelType $type The model type
     * @param string $publisher The model publisher
     * @param string $arch The model architecture
     * @param string $compatibilityType The compatibility type
     * @param string $quantization The quantization level
     * @param ModelState $state The model state
     * @param int $maxContextLength The maximum context length
     */
    public function __construct(
        string $id,
        string $object,
        ModelType $type,
        string $publisher,
        string $arch,
        string $compatibilityType,
        string $quantization,
        ModelState $state,
        int $maxContextLength
    ) {
        $this->id = $id;
        $this->object = $object;
        $this->type = $type;
        $this->publisher = $publisher;
        $this->arch = $arch;
        $this->compatibilityType = $compatibilityType;
        $this->quantization = $quantization;
        $this->state = $state;
        $this->maxContextLength = $maxContextLength;
    }

    /**
     * Create a ModelInfo from an array.
     *
     * @param array $data The model data
     * @return self
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
     *
     * @return array
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
     * Get the model ID.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
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
     * Get the model type.
     *
     * @return ModelType
     */
    public function getType(): ModelType
    {
        return $this->type;
    }

    /**
     * Get the model publisher.
     *
     * @return string
     */
    public function getPublisher(): string
    {
        return $this->publisher;
    }

    /**
     * Get the model architecture.
     *
     * @return string
     */
    public function getArch(): string
    {
        return $this->arch;
    }

    /**
     * Get the compatibility type.
     *
     * @return string
     */
    public function getCompatibilityType(): string
    {
        return $this->compatibilityType;
    }

    /**
     * Get the quantization level.
     *
     * @return string
     */
    public function getQuantization(): string
    {
        return $this->quantization;
    }

    /**
     * Get the model state.
     *
     * @return ModelState
     */
    public function getState(): ModelState
    {
        return $this->state;
    }

    /**
     * Get the maximum context length.
     *
     * @return int
     */
    public function getMaxContextLength(): int
    {
        return $this->maxContextLength;
    }

    /**
     * Check if the model is loaded.
     *
     * @return bool
     */
    public function isLoaded(): bool
    {
        return $this->state === ModelState::LOADED;
    }
}