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
     * Accumulate content from a streaming response.
     *
     * @param  \Generator  $stream  The streaming response
     */
    protected function accumulateContent(\Generator $stream): string
    {
        $content = '';

        foreach ($stream as $chunk) {
            if (isset($chunk['choices'][0]['delta']['content'])) {
                $content .= $chunk['choices'][0]['delta']['content'];
            } elseif (isset($chunk['choices'][0]['text'])) {
                $content .= $chunk['choices'][0]['text'];
            }
        }

        return $content;
    }

    /**
     * Accumulate tool calls from a streaming response.
     *
     * @param  \Generator  $stream  The streaming response
     */
    protected function accumulateToolCalls(\Generator $stream): array
    {
        $toolCalls = [];
        $currentToolCall = null;

        foreach ($stream as $chunk) {
            if (isset($chunk['choices'][0]['delta']['tool_calls'])) {
                $delta = $chunk['choices'][0]['delta']['tool_calls'][0];

                // Initialize a new tool call if we have an ID
                if (isset($delta['index']) && ! isset($toolCalls[$delta['index']])) {
                    $toolCalls[$delta['index']] = [
                        'id' => $delta['id'] ?? '',
                        'type' => $delta['type'] ?? 'function',
                        'function' => [
                            'name' => '',
                            'arguments' => '',
                        ],
                    ];
                    $currentToolCall = $delta['index'];
                }

                // Update the function name if present
                if (isset($delta['function']['name']) && $currentToolCall !== null) {
                    $toolCalls[$currentToolCall]['function']['name'] = $delta['function']['name'];
                }

                // Append to the arguments if present
                if (isset($delta['function']['arguments']) && $currentToolCall !== null) {
                    $toolCalls[$currentToolCall]['function']['arguments'] .= $delta['function']['arguments'];
                }
            }
        }

        return array_values($toolCalls);
    }

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
