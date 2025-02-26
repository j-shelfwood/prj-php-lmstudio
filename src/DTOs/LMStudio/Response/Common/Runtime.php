<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\LMStudio\Response\Common;

use JsonSerializable;

final readonly class Runtime implements JsonSerializable
{
    /**
     * @param  array<string>|null  $supportedFormats
     */
    public function __construct(
        public ?string $name = null,
        public ?string $version = null,
        public ?array $supportedFormats = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            version: $data['version'] ?? null,
            supportedFormats: $data['supported_formats'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'name' => $this->name,
            'version' => $this->version,
            'supported_formats' => $this->supportedFormats,
        ], fn ($value) => $value !== null);
    }
}
