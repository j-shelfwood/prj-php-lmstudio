<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model;

use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Exception\ValidationException;

class Message
{
    private Role $role;
    private ?string $content;
    private ?array $toolCalls;
    private ?string $toolCallId;
    private ?string $name;

    /**
     * @param Role $role The role of the message
     * @param string|null $content The content of the message
     * @param array|null $toolCalls Tool calls in the message
     * @param string|null $toolCallId The ID of the tool call
     * @param string|null $name The name of the function
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

    /**
     * Create a Message from an array.
     *
     * @param array $data The message data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $role = Role::from($data['role']);
        $content = $data['content'] ?? null;
        $toolCalls = $data['tool_calls'] ?? null;
        $toolCallId = $data['tool_call_id'] ?? null;
        $name = $data['name'] ?? null;

        return new self($role, $content, $toolCalls, $toolCallId, $name);
    }

    /**
     * Convert the message to an array.
     *
     * @return array
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
            $data['tool_calls'] = $this->toolCalls;
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
     *
     * @return Role
     */
    public function getRole(): Role
    {
        return $this->role;
    }

    /**
     * Get the content of the message.
     *
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Get the tool calls in the message.
     *
     * @return array|null
     */
    public function getToolCalls(): ?array
    {
        return $this->toolCalls;
    }

    /**
     * Get the tool call ID.
     *
     * @return string|null
     */
    public function getToolCallId(): ?string
    {
        return $this->toolCallId;
    }

    /**
     * Get the name of the function.
     *
     * @return string|null
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