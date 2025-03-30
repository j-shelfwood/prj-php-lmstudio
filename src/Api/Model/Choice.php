<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model;

use Shelfwood\LMStudio\Api\Enum\FinishReason;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;

/**
 * Represents a choice in a completion response.
 */
class Choice
{
    /**
     * @param  int  $index  The index of the choice
     * @param  array|null  $logprobs  The log probabilities
     * @param  FinishReason  $finishReason  The reason for finishing
     * @param  Message  $message  The message object
     */
    public function __construct(
        public readonly int $index,
        public readonly ?array $logprobs,
        public readonly FinishReason $finishReason,
        public readonly Message $message
    ) {
    }

    /**
     * Create a Choice object from an array.
     *
     * @param  array  $data  The choice data
     * @return self The created object
     */
    public static function fromArray(array $data): self
    {
        $finishReasonStr = $data['finish_reason'] ?? '';
        $finishReason = $finishReasonStr ? FinishReason::from($finishReasonStr) : FinishReason::STOP;

        $message = Message::fromArray($data['message'] ?? []);

        return new self(
            index: $data['index'] ?? 0,
            logprobs: $data['logprobs'] ?? null,
            finishReason: $finishReason,
            message: $message
        );
    }

    /**
     * Convert the choice to an array.
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'logprobs' => $this->logprobs,
            'finish_reason' => $this->finishReason->value,
            'message' => $this->message->toArray(),
        ];
    }

    /**
     * Get the content of the message.
     */
    public function getContent(): ?string
    {
        return $this->message->content;
    }

    /**
     * Check if the message has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return !empty($this->message->toolCalls);
    }

    /**
     * Get the tool calls from the message.
     *
     * @return array<ToolCall>
     */
    public function getToolCalls(): array
    {
        return $this->message->toolCalls ?? [];
    }
}
