<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model;

use Shelfwood\LMStudio\Api\Enum\FinishReason;

/**
 * Represents a choice within a streaming chat completion chunk.
 */
class ChoiceStreaming
{
    /**
     * @param  int  $index  The index of the choice
     * @param  ChoiceDelta  $delta  The delta content for this choice in the chunk
     * @param  FinishReason|null  $finishReason  The reason the stream finished (null if not finished)
     * @param  mixed  $logprobs  Log probabilities (structure may vary, keeping as mixed)
     */
    public function __construct(
        public readonly int $index,
        public readonly ChoiceDelta $delta,
        public readonly ?FinishReason $finishReason,
        public readonly mixed $logprobs = null
    ) {}

    /**
     * Creates a ChoiceStreaming object from raw choice data in a chunk.
     *
     * @param  array  $data  The choice data (e.g., $chunk['choices'][0])
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['index'])) {
            throw new \InvalidArgumentException('Streaming choice must include an index.');
        }

        if (! isset($data['delta'])) {
            throw new \InvalidArgumentException('Streaming choice must include a delta.');
        }

        $index = (int) $data['index'];
        $delta = ChoiceDelta::fromArray($data['delta']);
        $finishReason = isset($data['finish_reason']) ? FinishReason::from($data['finish_reason']) : null;
        $logprobs = $data['logprobs'] ?? null;

        return new self($index, $delta, $finishReason, $logprobs);
    }
}
