<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http\Responses\V0;

use Shelfwood\LMStudio\Http\Responses\Common\Choice;
use Shelfwood\LMStudio\Http\Responses\Common\Usage;

/**
 * Represents a chat completion response from the V0 API.
 */
class ChatCompletion
{
    /**
     * @param  string  $id  The ID of the completion
     * @param  string  $object  The object type
     * @param  int  $created  The timestamp when the completion was created
     * @param  string  $model  The model used for the completion
     * @param  array<Choice>  $choices  The choices in the completion
     * @param  Usage  $usage  The token usage information
     * @param  array<string, mixed>  $stats  Additional stats about the completion
     * @param  array<string, mixed>  $modelInfo  Information about the model
     * @param  array<string, mixed>  $runtime  Information about the runtime
     * @param  string|null  $systemFingerprint  The system fingerprint
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
        public readonly ?string $systemFingerprint = null,
    ) {}

    /**
     * Create a ChatCompletion object from an array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            object: $data['object'] ?? 'chat.completion',
            created: $data['created'] ?? time(),
            model: $data['model'] ?? '',
            choices: array_map(
                fn (array $choice) => Choice::fromArray($choice),
                $data['choices'] ?? []
            ),
            usage: Usage::fromArray($data['usage'] ?? []),
            stats: $data['stats'] ?? [],
            modelInfo: $data['model_info'] ?? [],
            runtime: $data['runtime'] ?? [],
            systemFingerprint: $data['system_fingerprint'] ?? null,
        );
    }
}
