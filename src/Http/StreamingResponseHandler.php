<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http;

use Generator;

/**
 * Handles streaming responses from the LMStudio API.
 */
class StreamingResponseHandler
{
    /**
     * Accumulate content from streaming chunks.
     *
     * @param  Generator  $stream  The stream of chunks
     */
    public function accumulateContent(Generator $stream): string
    {
        $content = '';

        foreach ($stream as $chunk) {
            if (isset($chunk['choices'][0]['delta']['content'])) {
                $content .= $chunk['choices'][0]['delta']['content'];
            }
        }

        return $content;
    }

    /**
     * Accumulate tool calls from streaming chunks.
     *
     * @param  Generator  $stream  The stream of chunks
     * @return array The accumulated tool calls
     */
    public function accumulateToolCalls(Generator $stream): array
    {
        $toolCalls = [];
        $currentToolCall = null;
        $currentId = null;
        $currentName = null;
        $currentArguments = '';

        foreach ($stream as $chunk) {
            if (! isset($chunk['choices'][0]['delta'])) {
                continue;
            }

            $delta = $chunk['choices'][0]['delta'];

            // Handle tool call initialization
            if (isset($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $toolCallDelta) {
                    // New tool call with ID
                    if (isset($toolCallDelta['id'])) {
                        $currentId = $toolCallDelta['id'];
                        $currentToolCall = [
                            'id' => $currentId,
                            'type' => $toolCallDelta['type'] ?? 'function',
                            'function' => [
                                'name' => '',
                                'arguments' => '',
                            ],
                        ];
                        $toolCalls[$currentId] = $currentToolCall;
                    }

                    // Update function name
                    if (isset($toolCallDelta['function']['name'])) {
                        $currentName = $toolCallDelta['function']['name'];
                        $toolCalls[$currentId]['function']['name'] = $currentName;
                    }

                    // Append to arguments
                    if (isset($toolCallDelta['function']['arguments'])) {
                        $currentArguments .= $toolCallDelta['function']['arguments'];
                        $toolCalls[$currentId]['function']['arguments'] = $currentArguments;
                    }
                }
            }
        }

        return array_values($toolCalls);
    }

    /**
     * Handle a streaming response.
     *
     * @param  Generator  $stream  The stream of chunks
     * @param  callable  $callback  The callback to handle each chunk
     */
    public function handle(Generator $stream, callable $callback): void
    {
        foreach ($stream as $chunk) {
            $callback($chunk);
        }
    }
}
