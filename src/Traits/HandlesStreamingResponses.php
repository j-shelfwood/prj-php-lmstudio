<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Traits;

use Generator;
use Shelfwood\LMStudio\Exceptions\StreamingException;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;

/**
 * Trait for handling streaming responses from LMStudio API.
 */
trait HandlesStreamingResponses
{
    /**
     * Get the streaming response handler instance.
     */
    protected function getStreamingResponseHandler(): StreamingResponseHandler
    {
        return new StreamingResponseHandler;
    }

    /**
     * Accumulate content from a streaming response.
     *
     * @param  \Generator  $stream  The streaming response
     * @return string The accumulated content
     *
     * @throws StreamingException If the streaming response fails
     */
    protected function accumulateContent(\Generator $stream): string
    {
        try {
            return $this->getStreamingResponseHandler()->accumulateContent($stream);
        } catch (\Exception $e) {
            if ($e instanceof StreamingException) {
                throw $e;
            }

            throw new StreamingException(
                "Failed to accumulate content from streaming response: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Accumulate tool calls from a streaming response.
     *
     * @param  \Generator  $stream  The streaming response
     * @return array The accumulated tool calls
     *
     * @throws StreamingException If the streaming response fails
     */
    protected function accumulateToolCalls(\Generator $stream): array
    {
        try {
            return $this->getStreamingResponseHandler()->accumulateToolCalls($stream);
        } catch (\Exception $e) {
            if ($e instanceof StreamingException) {
                throw $e;
            }

            throw new StreamingException(
                "Failed to accumulate tool calls from streaming response: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Process a streaming response with a callback for each chunk.
     *
     * @param  \Generator  $stream  The streaming response
     * @param  callable(mixed): void  $callback  The callback to process each chunk
     *
     * @throws StreamingException If the streaming response fails
     */
    protected function processStreamWithCallback(\Generator $stream, callable $callback): void
    {
        try {
            $this->getStreamingResponseHandler()->handle($stream, $callback);
        } catch (\Exception $e) {
            if ($e instanceof StreamingException) {
                throw $e;
            }

            throw new StreamingException(
                "Failed to process streaming response: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Create a streaming chat completion.
     *
     * @param  array  $messages  The messages to generate a completion for
     * @param  array  $options  Additional options for the completion
     * @return Generator The streaming response
     */
    public function streamChat(array $messages, array $options = []): Generator
    {
        // Perform health check if enabled
        if (isset($this->config) && $this->config->isHealthCheckEnabled()) {
            if (! $this->client->checkHealth()) {
                throw new StreamingException('LMStudio server is not available');
            }
        }

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
     * @return Generator The streaming response
     */
    public function streamCompletion(string $prompt, array $options = []): Generator
    {
        // Perform health check if enabled
        if (isset($this->config) && $this->config->isHealthCheckEnabled()) {
            if (! $this->client->checkHealth()) {
                throw new StreamingException('LMStudio server is not available');
            }
        }

        return $this->client->stream('completions', array_merge([
            'prompt' => $prompt,
            'stream' => true,
        ], $options));
    }
}
