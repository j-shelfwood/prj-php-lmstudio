<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model;

/**
 * Represents a single chunk received during a streaming chat completion.
 */
class ChatCompletionChunk
{
    /**
     * @param  string  $id  The chunk ID
     * @param  string  $object  The object type (usually 'chat.completion.chunk')
     * @param  int  $created  Timestamp of creation
     * @param  string  $model  The model used
     * @param  ChoiceStreaming[]  $choices  Array of choices in this chunk (usually just one)
     * @param  string|null  $systemFingerprint  System fingerprint
     * @param  Usage|null  $usage  Usage statistics (usually only in the last chunk)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $object,
        public readonly int $created,
        public readonly string $model,
        public readonly array $choices,
        public readonly ?string $systemFingerprint = null,
        public readonly ?Usage $usage = null
    ) {}

    /**
     * Creates a ChatCompletionChunk from raw chunk data.
     *
     * @param  array  $data  The raw associative array representing the chunk
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['id'], $data['object'], $data['created'], $data['model'], $data['choices'])) {
            throw new \InvalidArgumentException('Required fields missing in chat completion chunk data.');
        }

        $choices = array_map([ChoiceStreaming::class, 'fromArray'], $data['choices']);
        $usage = isset($data['usage']) ? Usage::fromArray($data['usage']) : null;

        return new self(
            id: $data['id'],
            object: $data['object'],
            created: (int) $data['created'],
            model: $data['model'],
            choices: $choices,
            systemFingerprint: $data['system_fingerprint'] ?? null,
            usage: $usage
        );
    }

    /**
     * Helper to check if this chunk indicates the end of the stream.
     * Checks the finish reason of the first choice.
     */
    public function isCompletionChunk(): bool
    {
        return isset($this->choices[0]) && $this->choices[0]->finishReason !== null;
    }
}
