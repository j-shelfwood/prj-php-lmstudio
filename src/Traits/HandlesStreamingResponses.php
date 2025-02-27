<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Traits;

use Generator;

/**
 * Trait for handling streaming responses from LMStudio API.
 */
trait HandlesStreamingResponses
{
    /**
     * Create a streaming chat completion.
     *
     * @param  array  $messages  The messages to generate a completion for
     * @param  array  $options  Additional options for the completion
     */
    public function streamChat(array $messages, array $options = []): Generator
    {
        return $this->client->stream('chat/completions', array_merge([
            'messages' => $messages,
            'stream' => true,
        ], $options));
    }

    /**
     * Create a streaming completion.
     *
     * @param  string  $prompt  The prompt to generate a completion for
     * @param  array  $options  Additional options for the completion
     */
    public function streamCompletion(string $prompt, array $options = []): Generator
    {
        return $this->client->stream('completions', array_merge([
            'prompt' => $prompt,
            'stream' => true,
        ], $options));
    }
}
