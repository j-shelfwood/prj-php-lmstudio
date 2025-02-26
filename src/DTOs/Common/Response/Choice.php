<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Common\Response;

use JsonSerializable;
use Shelfwood\LMStudio\DTOs\Common\Chat\Message;
use Shelfwood\LMStudio\DTOs\Common\Tool\ToolCall;

readonly class Choice implements JsonSerializable
{
    public function __construct(
        public readonly int $index,
        public readonly ?Message $message = null,
        public readonly ?array $toolCalls = null,
        public readonly ?string $finishReason = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $logprobs = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            index: $data['index'] ?? 0,
            message: isset($data['message']) ? Message::fromArray($data['message']) : null,
            toolCalls: isset($data['tool_calls']) ? array_map(
                fn (array $toolCall) => ToolCall::fromArray($toolCall),
                $data['tool_calls']
            ) : null,
            finishReason: $data['finish_reason'] ?? null,
            logprobs: $data['logprobs'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'index' => $this->index,
            'message' => $this->message?->jsonSerialize(),
            'tool_calls' => $this->toolCalls ? array_map(
                fn (ToolCall $toolCall) => $toolCall->jsonSerialize(),
                $this->toolCalls
            ) : null,
            'finish_reason' => $this->finishReason,
            'logprobs' => $this->logprobs,
        ], fn ($value) => $value !== null);
    }
}
