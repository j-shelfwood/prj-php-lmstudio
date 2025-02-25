<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Response\Common;

use JsonSerializable;

final readonly class Runtime implements JsonSerializable
{
    /**
     * @param  array<string>  $supportedFormats
     */
    public function __construct(
        public string $name,
        public string $version,
        public array $supportedFormats,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            version: $data['version'],
            supportedFormats: $data['supported_formats'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'supported_formats' => $this->supportedFormats,
        ];
    }
}
