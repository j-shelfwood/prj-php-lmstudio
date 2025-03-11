<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http\Responses\V0;

use Shelfwood\LMStudio\Enums\FinishReason;
use Shelfwood\LMStudio\Http\Responses\Common\Usage;

/**
 * Represents a text completion response from the V0 API.
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
     * @param  array<string, mixed>  $stats  Additional stats about the completion
     * @param  array<string, mixed>  $modelInfo  Information about the model
     * @param  array<string, mixed>  $runtime  Information about the runtime
     */
    public function __construct(
        public readonly string $id,
        public readonly string $object,
        public readonly int $created,
        public readonly string $model,
        public readonly array $choices,
        public readonly Usage $usage,
        public readonly array $stats,
        public readonly array $modelInfo,
        public readonly array $runtime,
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
            stats: $data['stats'] ?? [],
            modelInfo: $data['model_info'] ?? [],
            runtime: $data['runtime'] ?? [],
        );
    }
}
