<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObjects;

use Shelfwood\LMStudio\Enums\Role;

/**
 * Represents a message in a chat conversation.
 */
class Message implements \JsonSerializable
{
    /**
     * @param  Role  $role  The role of the message sender
     * @param  string|null  $content  The content of the message
     * @param  array<ToolCall>|null  $toolCalls  The tool calls in the message
     * @param  string|null  $name  The name of the message sender (optional)
     */
    public function __construct(
        public readonly Role $role,
        public readonly ?string $content = null,
        public readonly ?array $toolCalls = null,
        public readonly ?string $name = null,
    ) {
        // Validate that either content or toolCalls is provided for tool role
        if ($role === Role::TOOL && $content === null && empty($toolCalls)) {
            throw new \InvalidArgumentException('Tool messages must have either content or tool calls');
        }
    }

    /**
     * Create a system message.
     */
    public static function system(string $content): self
    {
        return new self(Role::SYSTEM, $content);
    }

    /**
     * Create a user message.
     */
    public static function user(string $content, ?string $name = null): self
    {
        return new self(Role::USER, $content, null, $name);
    }

    /**
     * Create an assistant message.
     */
    public static function assistant(string $content, ?array $toolCalls = null): self
    {
        return new self(Role::ASSISTANT, $content, $toolCalls);
    }

    /**
     * Create a tool message.
     */
    public static function tool(string $content, string $toolCallId): self
    {
        return new self(Role::TOOL, $content, null, $toolCallId);
    }

    /**
     * Convert the message to an array.
     */
    public function jsonSerialize(): array
    {
        $data = [
            'role' => $this->role->value,
        ];

        if ($this->content !== null) {
            $data['content'] = $this->content;
        }

        if ($this->toolCalls !== null) {
            $data['tool_calls'] = array_map(
                fn (ToolCall $toolCall) => $toolCall->jsonSerialize(),
                $this->toolCalls
            );
        }

        if ($this->name !== null) {
            if ($this->role === Role::TOOL) {
                $data['tool_call_id'] = $this->name;
            } else {
                $data['name'] = $this->name;
            }
        }

        return $data;
    }
}
