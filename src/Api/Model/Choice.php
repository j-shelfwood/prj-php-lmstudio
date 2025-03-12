<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model;

use Shelfwood\LMStudio\Api\Enum\FinishReason;

/**
 * Represents a choice in a completion response.
 */
class Choice
{
    /**
     * @param  int  $index  The index of the choice
     * @param  array|null  $logprobs  The log probabilities
     * @param  FinishReason  $finishReason  The reason for finishing
     * @param  array  $message  The message content
     */
    public function __construct(
        public readonly int $index,
        public readonly ?array $logprobs,
        public readonly FinishReason $finishReason,
        public readonly array $message
    ) {}

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

        return new self(
            index: $data['index'] ?? 0,
            logprobs: $data['logprobs'] ?? null,
            finishReason: $finishReason,
            message: $data['message'] ?? []
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
            'message' => $this->message,
        ];
    }

    /**
     * Get the index of the choice.
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * Get the log probabilities.
     */
    public function getLogprobs(): ?array
    {
        return $this->logprobs;
    }

    /**
     * Get the reason for finishing.
     */
    public function getFinishReason(): FinishReason
    {
        return $this->finishReason;
    }

    /**
     * Get the message content.
     */
    public function getMessage(): array
    {
        return $this->message;
    }

    /**
     * Get the content of the message.
     */
    public function getContent(): ?string
    {
        return $this->message['content'] ?? null;
    }

    /**
     * Check if the message has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return ! empty($this->message['tool_calls']);
    }

    /**
     * Get the tool calls from the message.
     */
    public function getToolCalls(): array
    {
        return $this->message['tool_calls'] ?? [];
    }
}
