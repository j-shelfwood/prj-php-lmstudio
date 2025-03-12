<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Model;

/**
 * Represents token usage information in a response.
 */
class Usage
{
    /**
     * @param  int  $promptTokens  The number of tokens in the prompt
     * @param  int  $completionTokens  The number of tokens in the completion
     * @param  int  $totalTokens  The total number of tokens
     */
    public function __construct(
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly int $totalTokens
    ) {}

    /**
     * Create a Usage object from an array.
     *
     * @param  array  $data  The usage data
     * @return self The created object
     */
    public static function fromArray(array $data): self
    {
        return new self(
            promptTokens: $data['prompt_tokens'] ?? 0,
            completionTokens: $data['completion_tokens'] ?? 0,
            totalTokens: $data['total_tokens'] ?? 0
        );
    }

    /**
     * Convert the usage to an array.
     */
    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }

    /**
     * Get the number of tokens in the prompt.
     */
    public function getPromptTokens(): int
    {
        return $this->promptTokens;
    }

    /**
     * Get the number of tokens in the completion.
     */
    public function getCompletionTokens(): int
    {
        return $this->completionTokens;
    }

    /**
     * Get the total number of tokens.
     */
    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }
}
