<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model;

/**
 * Represents model information in a response.
 */
class ResponseModelInfo
{
    /**
     * @param  string  $arch  The model architecture
     * @param  string  $quant  The quantization level
     * @param  string  $format  The model format
     * @param  int  $contextLength  The context length
     */
    public function __construct(
        public readonly string $arch,
        public readonly string $quant,
        public readonly string $format,
        public readonly int $contextLength
    ) {}

    /**
     * Create a ResponseModelInfo object from an array.
     *
     * @param  array|null  $data  The model info data
     * @return self|null The created object or null if data is null
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return new self(
            arch: $data['arch'] ?? '',
            quant: $data['quant'] ?? '',
            format: $data['format'] ?? '',
            contextLength: $data['context_length'] ?? 0
        );
    }

    /**
     * Convert the model info to an array.
     */
    public function toArray(): array
    {
        return [
            'arch' => $this->arch,
            'quant' => $this->quant,
            'format' => $this->format,
            'context_length' => $this->contextLength,
        ];
    }
}
