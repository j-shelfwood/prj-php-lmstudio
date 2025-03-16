<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model;

use Shelfwood\LMStudio\Api\Enum\FinishReason;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCallFormatter;

/**
 * Represents a choice in a completion response.
 */
class Choice
{
    private ?ToolCallFormatter $formatter = null;

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
    ) {
        $this->formatter = new ToolCallFormatter;
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
        return isset($this->message['tool_calls']) && ! empty($this->message['tool_calls'])
            || ($this->getContent() && strpos($this->getContent(), '<tool_call>') !== false);
    }

    /**
     * Get the tool calls from the message.
     *
     * @return array<ToolCall>
     */
    public function getToolCalls(): array
    {
        if (isset($this->message['tool_calls']) && ! empty($this->message['tool_calls'])) {
            $toolCalls = [];

            foreach ($this->message['tool_calls'] as $toolCall) {
                $toolCalls[] = ToolCall::fromArray($toolCall);
            }

            return $toolCalls;
        }

        $content = $this->getContent();

        if (! $content) {
            return [];
        }

        return $this->formatter->parseToolCalls($content);
    }
}
