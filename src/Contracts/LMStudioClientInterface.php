<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Contracts;

use Generator;

interface LMStudioClientInterface
{
    /**
     * List available models.
     */
    public function models(): array;

    /**
     * Create a chat completion.
     *
     * @param  array  $messages  The messages to generate a completion for
     * @param  array  $options  Additional options for the completion
     * @return mixed The chat completion response (type varies by implementation)
     */
    public function chat(array $messages, array $options = []): mixed;

    /**
     * Create a streaming chat completion.
     *
     * @param  array  $messages  The messages to generate a completion for
     * @param  array  $options  Additional options for the completion
     */
    public function streamChat(array $messages, array $options = []): Generator;

    /**
     * Create a completion.
     *
     * @param  string  $prompt  The prompt to generate a completion for
     * @param  array  $options  Additional options for the completion
     * @return mixed The text completion response (type varies by implementation)
     */
    public function completion(string $prompt, array $options = []): mixed;

    /**
     * Create a streaming completion.
     *
     * @param  string  $prompt  The prompt to generate a completion for
     * @param  array  $options  Additional options for the completion
     */
    public function streamCompletion(string $prompt, array $options = []): Generator;

    /**
     * Create embeddings.
     *
     * @param  string|array  $input  The text to create embeddings for
     * @param  array  $options  Additional options for the embeddings
     * @return mixed The embedding response (type varies by implementation)
     */
    public function embeddings(string|array $input, array $options = []): mixed;
}
