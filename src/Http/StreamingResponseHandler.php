<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http;

use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * Handles streaming responses from the LMStudio API.
 */
class StreamingResponseHandler
{
    /**
     * @var StreamInterface The PSR-7 stream
     */
    private StreamInterface $stream;

    /**
     * Create a new StreamingResponseHandler instance
     */
    public function __construct(StreamInterface $stream)
    {
        $this->stream = $stream;
    }

    /**
     * Process the stream and yield JSON decoded chunks
     */
    public function stream(): Generator
    {
        $buffer = '';

        while (! $this->stream->eof()) {
            $chunk = $this->stream->read(1024);

            if (! empty($chunk)) {
                $buffer .= $chunk;

                // Process complete SSE messages
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $message = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    foreach (explode("\n", $message) as $line) {
                        if (str_starts_with($line, 'data: ')) {
                            $data = substr($line, 6);

                            if ($data === '[DONE]') {
                                return;
                            }

                            $decoded = json_decode($data, true);

                            if ($decoded !== null) {
                                yield $decoded;
                            }
                        }
                    }
                }
            } else {
                // Small sleep to prevent CPU spinning
                usleep(50000); // 50ms
            }
        }
    }

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
        $argumentsBuffer = '';
        $inToolCall = false;

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
                        // If we were building a previous tool call, try to parse its arguments
                        if ($currentId !== null && ! empty($argumentsBuffer)) {
                            $parsed = json_decode($argumentsBuffer, true);

                            if (json_last_error() === JSON_ERROR_NONE) {
                                $toolCalls[$currentId]['function']['arguments_parsed'] = $parsed;
                            } else {
                                // Keep raw arguments if parsing fails
                                $toolCalls[$currentId]['function']['arguments_raw'] = $argumentsBuffer;
                            }
                        }

                        $currentId = $toolCallDelta['id'];
                        $argumentsBuffer = '';
                        $inToolCall = true;
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
                        $toolCalls[$currentId]['function']['name'] = $toolCallDelta['function']['name'];
                    }

                    // Append to arguments
                    if (isset($toolCallDelta['function']['arguments'])) {
                        $argumentsBuffer .= $toolCallDelta['function']['arguments'];
                        $toolCalls[$currentId]['function']['arguments'] = $argumentsBuffer;

                        // Try to parse JSON incrementally
                        if (! empty($argumentsBuffer)) {
                            $parsed = json_decode($argumentsBuffer, true);

                            if (json_last_error() === JSON_ERROR_NONE) {
                                $toolCalls[$currentId]['function']['arguments_parsed'] = $parsed;
                            }
                        }
                    }
                }
            }
        }

        // Final attempt to parse any remaining arguments
        if ($currentId !== null && ! empty($argumentsBuffer)) {
            $parsed = json_decode($argumentsBuffer, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $toolCalls[$currentId]['function']['arguments_parsed'] = $parsed;
            } else {
                // Keep raw arguments if parsing fails
                $toolCalls[$currentId]['function']['arguments_raw'] = $argumentsBuffer;
            }
        }

        return array_values($toolCalls);
    }

    /**
     * Handle a streaming response by calling a callback for each chunk.
     *
     * @param  Generator  $stream  The stream of chunks
     * @param  callable(array<string, mixed>): void  $callback  The callback to handle each chunk
     */
    public function handle(Generator $stream, callable $callback): void
    {
        foreach ($stream as $chunk) {
            $callback($chunk);
        }
    }
}
