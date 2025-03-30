<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model;

/**
 * Represents runtime information in a response.
 */
class RuntimeInfo
{
    /**
     * @param  string  $name  The runtime name
     * @param  string  $version  The runtime version
     * @param  array  $supportedFormats  The supported formats
     */
    public function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly array $supportedFormats
    ) {}

    /**
     * Create a RuntimeInfo object from an array.
     *
     * @param  array|null  $data  The runtime data
     * @return self|null The created object or null if data is null
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return new self(
            name: $data['name'] ?? '',
            version: $data['version'] ?? '',
            supportedFormats: $data['supported_formats'] ?? []
        );
    }

    /**
     * Convert the runtime info to an array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'supported_formats' => $this->supportedFormats,
        ];
    }
}
