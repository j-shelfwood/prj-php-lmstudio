<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Response;

use JsonSerializable;
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Tool\ToolCall;

final readonly class StreamingResponse implements JsonSerializable
{
    /**
     * Create a new streaming response instance
     */
    public function __construct(
        public string $id,
        public string $type, // 'message', 'tool_call', 'done'
        public ?Message $message = null,
        public ?ToolCall $toolCall = null,
    ) {}

    /**
     * Create a streaming response from a chat message
     */
    public static function fromMessage(Message $message): self
    {
        return new self(
            id: uniqid('stream_'),
            type: 'message',
            message: $message,
        );
    }

    /**
     * Create a streaming response from a tool call
     */
    public static function fromToolCall(ToolCall $toolCall): self
    {
        return new self(
            id: uniqid('stream_'),
            type: 'tool_call',
            toolCall: $toolCall,
        );
    }

    /**
     * Create a streaming response indicating the end of the stream
     */
    public static function done(): self
    {
        return new self(
            id: uniqid('stream_'),
            type: 'done',
        );
    }

    /**
     * Convert the object to a JSON serializable array
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'id' => $this->id,
            'type' => $this->type,
            'message' => $this->message?->jsonSerialize(),
            'tool_call' => $this->toolCall?->jsonSerialize(),
        ], fn ($value) => $value !== null);
    }
}
