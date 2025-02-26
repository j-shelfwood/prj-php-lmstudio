<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Common\Response;

use Shelfwood\LMStudio\DTOs\Common\Chat\Message;
use Shelfwood\LMStudio\DTOs\Common\Tool\ToolCall;

class StreamingResponse
{
    public function __construct(
        public readonly string $type,
        public readonly ?Message $message = null,
        public readonly ?ToolCall $toolCall = null,
        public readonly bool $done = false
    ) {}

    public static function fromMessage(Message $message): self
    {
        return new self(
            type: 'message',
            message: $message
        );
    }

    public static function fromToolCall(ToolCall $toolCall): self
    {
        return new self(
            type: 'tool_call',
            toolCall: $toolCall
        );
    }

    public static function done(): self
    {
        return new self(
            type: 'done',
            done: true
        );
    }
}
