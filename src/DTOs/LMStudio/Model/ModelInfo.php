<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\LMStudio\Model;

use JsonSerializable;

final readonly class ModelInfo implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $object,
        public string $type,
        public ?string $publisher,
        public ?string $arch,
        public ?string $compatibilityType,
        public ?string $quantization,
        public string $state,
        public ?int $maxContextLength,
        public ?int $created = null,
        public ?string $ownedBy = null,
        public array $permission = [],
        public ?string $root = null,
        public ?string $parent = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            object: $data['object'],
            type: $data['type'] ?? 'llm',
            publisher: $data['publisher'] ?? null,
            arch: $data['arch'] ?? null,
            compatibilityType: $data['compatibility_type'] ?? null,
            quantization: $data['quantization'] ?? null,
            state: $data['state'] ?? 'not-loaded',
            maxContextLength: $data['max_context_length'] ?? null,
            created: $data['created'] ?? null,
            ownedBy: $data['owned_by'] ?? null,
            permission: $data['permission'] ?? [],
            root: $data['root'] ?? null,
            parent: $data['parent'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'id' => $this->id,
            'object' => $this->object,
            'type' => $this->type,
            'publisher' => $this->publisher,
            'arch' => $this->arch,
            'compatibility_type' => $this->compatibilityType,
            'quantization' => $this->quantization,
            'state' => $this->state,
            'max_context_length' => $this->maxContextLength,
            'created' => $this->created,
            'owned_by' => $this->ownedBy,
            'permission' => $this->permission,
            'root' => $this->root,
            'parent' => $this->parent,
        ], fn ($value) => $value !== null);
    }
}
