<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http\Response\V1;

use Shelfwood\LMStudio\Http\Response\Common\Choice;
use Shelfwood\LMStudio\Http\Response\Common\Usage;

/**
 * Represents a chat completion response from the V1 API.
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
     * @param  string|null  $systemFingerprint  The system fingerprint
     */
    public function __construct(
        public readonly string $id,
        public readonly string $object,
        public readonly int $created,
        public readonly string $model,
        public readonly array $choices,
        public readonly Usage $usage,
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
            systemFingerprint: $data['system_fingerprint'] ?? null,
        );
    }
}
