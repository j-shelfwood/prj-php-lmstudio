<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Response\Common;

use JsonSerializable;

final readonly class Message implements JsonSerializable
{
    /**
     * @param  array<ToolCall>|null  $toolCalls
     */
    public function __construct(
        public string $role,
        public ?string $content = null,
        public ?array $toolCalls = null,
        public ?FunctionCall $functionCall = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            role: $data['role'] ?? 'assistant',
            content: $data['content'] ?? null,
            toolCalls: isset($data['tool_calls'])
                ? array_map(fn ($tc) => ToolCall::fromArray($tc), $data['tool_calls'])
                : null,
            functionCall: isset($data['function_call'])
                ? FunctionCall::fromArray($data['function_call'])
                : null,
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'role' => $this->role,
            'content' => $this->content,
            'tool_calls' => $this->toolCalls ? array_map(fn ($tc) => $tc->jsonSerialize(), $this->toolCalls) : null,
            'function_call' => $this->functionCall?->jsonSerialize(),
        ], fn ($value) => $value !== null);
    }
}
