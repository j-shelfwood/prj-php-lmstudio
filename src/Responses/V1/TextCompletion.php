<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Responses\V1;

use Shelfwood\LMStudio\Enums\FinishReason;
use Shelfwood\LMStudio\Responses\Common\Usage;

/**
 * Represents a text completion response from the V1 API.
 */
class TextCompletion
{
    /**
     * @param  string  $id  The ID of the completion
     * @param  string  $object  The object type
     * @param  int  $created  The timestamp when the completion was created
     * @param  string  $model  The model used for the completion
     * @param  array<array{text: string, index: int, logprobs: ?array<string, mixed>, finish_reason: string}>  $choices  The choices in the completion
     * @param  Usage  $usage  The token usage information
     */
    public function __construct(
        public readonly string $id,
        public readonly string $object,
        public readonly int $created,
        public readonly string $model,
        public readonly array $choices,
        public readonly Usage $usage,
    ) {}

    /**
     * Create a TextCompletion object from an array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            object: $data['object'] ?? 'text_completion',
            created: $data['created'] ?? time(),
            model: $data['model'] ?? '',
            choices: array_map(
                fn (array $choice) => [
                    'text' => $choice['text'] ?? '',
                    'index' => $choice['index'] ?? 0,
                    'logprobs' => $choice['logprobs'] ?? null,
                    'finish_reason' => $choice['finish_reason'] ?? FinishReason::STOP->value,
                ],
                $data['choices'] ?? []
            ),
            usage: Usage::fromArray($data['usage'] ?? []),
        );
    }
}
