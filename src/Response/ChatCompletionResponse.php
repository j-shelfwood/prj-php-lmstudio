<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Response;

/**
 * Represents a chat completion response.
 */
class ChatCompletionResponse
{
    /**
     * @param  string  $id  The ID of the completion
     * @param  string  $object  The object type
     * @param  int  $created  The timestamp when the completion was created
     * @param  string  $model  The model used for the completion
     * @param  array  $choices  The choices in the completion
     * @param  array  $usage  The token usage information
     * @param  string|null  $systemFingerprint  The system fingerprint
     */
    public function __construct(
        public readonly string $id,
        public readonly string $object,
        public readonly int $created,
        public readonly string $model,
        public readonly array $choices,
        public readonly array $usage,
        public readonly ?string $systemFingerprint = null,
    ) {}

    /**
     * Create a ChatCompletionResponse object from an array.
     *
     * @param  array  $data  The response data
     * @return self The created object
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            object: $data['object'] ?? 'chat.completion',
            created: $data['created'] ?? time(),
            model: $data['model'] ?? '',
            choices: $data['choices'] ?? [],
            usage: $data['usage'] ?? [],
            systemFingerprint: $data['system_fingerprint'] ?? null,
        );
    }
}
