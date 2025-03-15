<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model;

use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Exception\ValidationException;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;

class Message
{
    private Role $role;

    private ?string $content;

    /** @var ToolCall[]|null */
    private ?array $toolCalls;

    private ?string $toolCallId;

    private ?string $name;

    /**
     * @param  Role  $role  The role of the message
     * @param  string|null  $content  The content of the message
     * @param  ToolCall[]|null  $toolCalls  Tool calls in the message
     * @param  string|null  $toolCallId  The ID of the tool call
     * @param  string|null  $name  The name of the function
     */
    public function __construct(
        Role $role,
        ?string $content = null,
        ?array $toolCalls = null,
        ?string $toolCallId = null,
        ?string $name = null
    ) {
        $this->role = $role;
        $this->content = $content;
        $this->toolCalls = $toolCalls;
        $this->toolCallId = $toolCallId;
        $this->name = $name;

        $this->validate();
    }

    public static function forToolCall(ToolCall $toolCall): self
    {
        return new self(Role::ASSISTANT, null, [$toolCall]);
    }

    public static function forToolResponse(string $result, string $toolCallId): self
    {
        return new self(Role::TOOL, $result, null, $toolCallId);
    }

    public static function forUser(string $content): self
    {
        return new self(Role::USER, $content);
    }

    public static function forSystem(string $content): self
    {
        return new self(Role::SYSTEM, $content);
    }

    /**
     * Create a Message from an array.
     *
     * @param  array  $data  The message data
     */
    public static function fromArray(array $data): self
    {
        $role = Role::from($data['role']);
        $content = $data['content'] ?? null;
        $toolCalls = isset($data['tool_calls'])
            ? array_map([ToolCall::class, 'fromArray'], $data['tool_calls'])
            : null;
        $toolCallId = $data['tool_call_id'] ?? null;
        $name = $data['name'] ?? null;

        return new self($role, $content, $toolCalls, $toolCallId, $name);
    }

    /**
     * Convert the message to an array.
     */
    public function toArray(): array
    {
        $data = [
            'role' => $this->role->value,
        ];

        if ($this->content !== null) {
            $data['content'] = $this->content;
        }

        if ($this->toolCalls !== null) {
            $data['tool_calls'] = array_map(fn (ToolCall $call) => $call->toArray(), $this->toolCalls);
        }

        if ($this->toolCallId !== null) {
            $data['tool_call_id'] = $this->toolCallId;
        }

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        return $data;
    }

    /**
     * Get the role of the message.
     */
    public function getRole(): Role
    {
        return $this->role;
    }

    /**
     * Get the content of the message.
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Get the tool calls in the message.
     *
     * @return ToolCall[]|null
     */
    public function getToolCalls(): ?array
    {
        return $this->toolCalls;
    }

    /**
     * Get the tool call ID.
     */
    public function getToolCallId(): ?string
    {
        return $this->toolCallId;
    }

    /**
     * Get the name of the function.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Validate the message.
     *
     * @throws ValidationException If the message is invalid
     */
    private function validate(): void
    {
        // Content is required for system and user roles
        if (($this->role === Role::SYSTEM || $this->role === Role::USER) && $this->content === null) {
            throw new ValidationException('Content is required for system and user messages');
        }

        // Tool call ID and name are required for tool role
        if ($this->role === Role::TOOL) {
            if ($this->content === null) {
                throw new ValidationException('Content is required for tool messages');
            }

            if ($this->toolCallId === null) {
                throw new ValidationException('Tool call ID is required for tool messages');
            }
        }

        // Assistant role can have either content or tool calls
        if ($this->role === Role::ASSISTANT && $this->content === null && $this->toolCalls === null) {
            throw new ValidationException('Assistant messages must have either content or tool calls');
        }
    }
}
