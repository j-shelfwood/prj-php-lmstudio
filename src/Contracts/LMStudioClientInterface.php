<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Contracts;

use Generator;
use Shelfwood\LMStudio\Requests\Common\RequestInterface;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;

interface LMStudioClientInterface
{
    /**
     * List available models.
     */
    public function models(): array;

    /**
     * Create a chat completion.
     *
     * @param  RequestInterface  $request  The chat completion request
     * @return mixed The chat completion response (type varies by implementation)
     */
    public function chatCompletion(RequestInterface $request): mixed;

    /**
     * Create a streaming chat completion.
     *
     * @param  RequestInterface  $request  The chat completion request
     */
    public function streamChatCompletion(RequestInterface $request): Generator;

    /**
     * Create a text completion.
     *
     * @param  RequestInterface  $request  The text completion request
     * @return mixed The text completion response (type varies by implementation)
     */
    public function textCompletion(RequestInterface $request): mixed;

    /**
     * Create a streaming text completion.
     *
     * @param  RequestInterface  $request  The text completion request
     */
    public function streamTextCompletion(RequestInterface $request): Generator;

    /**
     * Create embeddings.
     *
     * @param  RequestInterface  $request  The embedding request
     * @return mixed The embedding response (type varies by implementation)
     */
    public function createEmbeddings(RequestInterface $request): mixed;

    /**
     * Create a chat completion (legacy method).
     *
     * @param  array  $messages  The messages to generate a completion for
     * @param  array  $options  Additional options for the completion
     * @return mixed The chat completion response (type varies by implementation)
     *
     * @deprecated Use chatCompletion() instead
     */
    public function chat(array $messages, array $options = []): mixed;

    /**
     * Create a streaming chat completion (legacy method).
     *
     * @param  array  $messages  The messages to generate a completion for
     * @param  array  $options  Additional options for the completion
     *
     * @deprecated Use streamChatCompletion() instead
     */
    public function streamChat(array $messages, array $options = []): Generator;

    /**
     * Create a completion (legacy method).
     *
     * @param  string  $prompt  The prompt to generate a completion for
     * @param  array  $options  Additional options for the completion
     * @return mixed The text completion response (type varies by implementation)
     *
     * @deprecated Use textCompletion() instead
     */
    public function completion(string $prompt, array $options = []): mixed;

    /**
     * Create a streaming completion (legacy method).
     *
     * @param  string  $prompt  The prompt to generate a completion for
     * @param  array  $options  Additional options for the completion
     *
     * @deprecated Use streamTextCompletion() instead
     */
    public function streamCompletion(string $prompt, array $options = []): Generator;

    /**
     * Create embeddings (legacy method).
     *
     * @param  string|array  $input  The text to create embeddings for
     * @param  array  $options  Additional options for the embeddings
     * @return mixed The embedding response (type varies by implementation)
     *
     * @deprecated Use createEmbeddings() instead
     */
    public function embeddings(string|array $input, array $options = []): mixed;

    /**
     * Accumulate content from a streaming chat completion.
     *
     * @param  array|ChatHistory  $messages  The messages to generate a completion for
     * @param  array  $options  Additional options for the completion
     */
    public function accumulateChatContent(array|ChatHistory $messages, array $options = []): string;

    /**
     * Accumulate tool calls from a streaming chat completion.
     *
     * @param  array|ChatHistory  $messages  The messages to generate a completion for
     * @param  array  $options  Additional options for the completion
     */
    public function accumulateChatToolCalls(array|ChatHistory $messages, array $options = []): array;

    /**
     * Accumulate content from a streaming text completion.
     *
     * @param  string  $prompt  The prompt to generate a completion for
     * @param  array  $options  Additional options for the completion
     */
    public function accumulateCompletionContent(string $prompt, array $options = []): string;
}
