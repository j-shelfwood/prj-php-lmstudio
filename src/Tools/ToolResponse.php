<?php

namespace Shelfwood\LMStudio\Tools;

/**
 * Represents a response from a tool execution.
 */
class ToolResponse implements \JsonSerializable
{
    /**
     * Create a new tool response.
     */
    public function __construct(
        public readonly string $toolCallId,
        public readonly string $toolName,
        public readonly string $content,
        public readonly string $status = 'success',
        public readonly ?int $progress = null,
        public readonly ?string $error = null
    ) {
    }

    /**
     * Create a success response.
     */
    public static function success(string $toolCallId, string $toolName, string $content): self
    {
        return new self(
            toolCallId: $toolCallId,
            toolName: $toolName,
            content: $content,
            status: 'success'
        );
    }

    /**
     * Create an error response.
     */
    public static function error(string $toolCallId, string $toolName, string $error): self
    {
        return new self(
            toolCallId: $toolCallId,
            toolName: $toolName,
            content: "Error: {$error}",
            status: 'error',
            error: $error
        );
    }

    /**
     * Create a progress response.
     */
    public static function progress(string $toolCallId, string $toolName, int $progress, string $content = ''): self
    {
        return new self(
            toolCallId: $toolCallId,
            toolName: $toolName,
            content: $content,
            status: 'in_progress',
            progress: $progress
        );
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'tool_call_id' => $this->toolCallId,
            'tool_name' => $this->toolName,
            'content' => $this->content,
            'status' => $this->status,
            'progress' => $this->progress,
            'error' => $this->error,
        ], fn ($value) => $value !== null);
    }
}