<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\OpenAI\Model;

use JsonSerializable;

final readonly class ModelInfo implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $object,
        public ?int $created,
        public string $ownedBy,
        public array $permission = [],
        public ?string $root = null,
        public ?string $parent = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            object: $data['object'],
            created: $data['created'] ?? null,
            ownedBy: $data['owned_by'],
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
            'created' => $this->created,
            'owned_by' => $this->ownedBy,
            'permission' => $this->permission,
            'root' => $this->root,
            'parent' => $this->parent,
        ], fn ($value) => $value !== null);
    }
}
