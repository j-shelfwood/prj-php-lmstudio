<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Responses\Common;

/**
 * Represents token usage information in a response.
 */
class Usage
{
    /**
     * @param  int  $promptTokens  The number of tokens in the prompt
     * @param  int  $completionTokens  The number of tokens in the completion
     * @param  int  $totalTokens  The total number of tokens used
     */
    public function __construct(
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly int $totalTokens,
    ) {}

    /**
     * Create a Usage object from an array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            promptTokens: $data['prompt_tokens'] ?? 0,
            completionTokens: $data['completion_tokens'] ?? 0,
            totalTokens: $data['total_tokens'] ?? 0,
        );
    }
}
