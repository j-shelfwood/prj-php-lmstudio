<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Response;

/**
 * Represents a text completion response.
 */
class TextCompletionResponse
{
    /**
     * @param  string  $id  The ID of the completion
     * @param  string  $object  The object type
     * @param  int  $created  The timestamp when the completion was created
     * @param  string  $model  The model used for the completion
     * @param  list<array>  $choices  The choices in the completion (raw array data)
     * @param  array<string, int>  $usage  The token usage information
     */
    public function __construct(
        public readonly string $id,
        public readonly string $object,
        public readonly int $created,
        public readonly string $model,
        /** @var list<array{index: int, text: string, logprobs: null, finish_reason: string}> */
        public readonly array $choices,
        /** @var array<string, int> */
        public readonly array $usage,
    ) {}

    /**
     * Create a TextCompletionResponse object from an array.
     *
     * @param  array  $data  The response data
     * @return self The created object
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            object: $data['object'] ?? 'text_completion',
            created: $data['created'] ?? time(),
            model: $data['model'] ?? '',
            choices: $data['choices'] ?? [], // Assign raw choices array directly
            usage: $data['usage'] ?? [],
        );
    }

    /**
     * Get the choices in the completion (as raw arrays).
     *
     * @return list<array{index: int, text: string, logprobs: null, finish_reason: string}> The choices array.
     */
    public function getChoices(): array
    {
        return $this->choices;
    }

    /**
     * Get the text from the first choice.
     */
    public function getText(): ?string
    {
        // Access the 'text' key directly from the first choice array
        return $this->choices[0]['text'] ?? null;
    }
}
