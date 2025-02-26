<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Common\Chat;

use JsonSerializable;

final readonly class Message implements JsonSerializable
{
    public function __construct(
        public Role $role,
        public string $content,
        public ?array $toolCalls = null,
        public ?string $toolCallId = null,
        public ?string $name = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            role: Role::from($data['role']),
            content: $data['content'],
            toolCalls: $data['tool_calls'] ?? null,
            toolCallId: $data['tool_call_id'] ?? null,
            name: $data['name'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'role' => $this->role->value,
            'content' => $this->content,
            'tool_calls' => $this->toolCalls,
            'tool_call_id' => $this->toolCallId,
            'name' => $this->name,
        ], fn ($value) => $value !== null);
    }
}
