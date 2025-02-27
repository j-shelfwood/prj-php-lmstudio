<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Responses\Common;

use Shelfwood\LMStudio\Enums\FinishReason;

/**
 * Represents a choice in a response.
 */
class Choice
{
    /**
     * @param  int  $index  The index of the choice
     * @param  Message  $message  The message in the choice
     * @param  FinishReason  $finishReason  The reason the generation finished
     * @param  array|null  $logprobs  The log probabilities of the tokens
     */
    public function __construct(
        public readonly int $index,
        public readonly Message $message,
        public readonly FinishReason $finishReason,
        public readonly ?array $logprobs = null,
    ) {}

    /**
     * Create a Choice object from an array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            index: $data['index'] ?? 0,
            message: Message::fromArray($data['message'] ?? []),
            finishReason: FinishReason::from($data['finish_reason'] ?? 'stop'),
            logprobs: $data['logprobs'] ?? null,
        );
    }
}
