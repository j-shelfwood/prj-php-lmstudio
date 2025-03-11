<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http\Responses\Common;

use Shelfwood\LMStudio\Enums\Role;

/**
 * Represents a message in a response.
 */
class Message
{
    /**
     * @param  Role  $role  The role of the message sender
     * @param  string|null  $content  The content of the message
     * @param  array<ToolCall>|null  $toolCalls  The tool calls in the message
     */
    public function __construct(
        public readonly Role $role,
        public readonly ?string $content = null,
        public readonly ?array $toolCalls = null,
    ) {}

    /**
     * Create a Message object from an array.
     */
    public static function fromArray(array $data): self
    {
        $toolCalls = null;

        if (isset($data['tool_calls']) && is_array($data['tool_calls'])) {
            $toolCalls = array_map(
                fn (array $toolCall) => ToolCall::fromArray($toolCall),
                $data['tool_calls']
            );
        }

        return new self(
            role: Role::from($data['role'] ?? 'assistant'),
            content: $data['content'] ?? null,
            toolCalls: $toolCalls,
        );
    }
}
