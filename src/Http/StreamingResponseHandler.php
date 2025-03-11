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
     * @param  Generator<\Shelfwood\LMStudio\ValueObjects\StreamChunk>  $stream  The stream of chunks
     */
    public function accumulateContent(Generator $stream): string
    {
        $content = '';

        foreach ($stream as $chunk) {
            if ($chunk->hasContent()) {
                $content .= $chunk->getContent();
            }
        }

        return $content;
    }

    /**
     * Accumulate tool calls from streaming chunks.
     *
     * @param  Generator<\Shelfwood\LMStudio\ValueObjects\StreamChunk>  $stream  The stream of chunks
     * @return array The accumulated tool calls
     */
    public function accumulateToolCalls(Generator $stream): array
    {
        $toolCalls = [];
        $currentId = null;
        $argumentsBuffer = '';

        foreach ($stream as $chunk) {
            if ($chunk->hasToolCalls()) {
                foreach ($chunk->getToolCalls() as $toolCall) {
                    $id = $toolCall->id;

                    // Initialize tool call if new
                    if (! isset($toolCalls[$id])) {
                        $toolCalls[$id] = [
                            'id' => $id,
                            'type' => $toolCall->type,
                            'function' => [
                                'name' => $toolCall->function->name,
                                'arguments' => '',
                            ],
                        ];
                        $currentId = $id;
                        $argumentsBuffer = '';
                    }

                    // Update function name if provided
                    if (! empty($toolCall->function->name)) {
                        $toolCalls[$id]['function']['name'] = $toolCall->function->name;
                    }

                    // Append arguments if provided
                    if (! empty($toolCall->function->arguments)) {
                        $argumentsBuffer .= $toolCall->function->arguments;
                        $toolCalls[$id]['function']['arguments'] = $argumentsBuffer;

                        // Try to parse JSON
                        $parsed = json_decode($argumentsBuffer, true);

                        if (json_last_error() === JSON_ERROR_NONE) {
                            $toolCalls[$id]['function']['arguments_parsed'] = $parsed;
                        }
                    }
                }
            }
        }

        return array_values($toolCalls);
    }

    /**
     * Handle a streaming response by calling a callback for each chunk.
     *
     * @param  Generator<\Shelfwood\LMStudio\ValueObjects\StreamChunk>  $stream  The stream of chunks
     * @param  callable(\Shelfwood\LMStudio\ValueObjects\StreamChunk): void  $callback  The callback to handle each chunk
     */
    public function handle(Generator $stream, callable $callback): void
    {
        foreach ($stream as $chunk) {
            $callback($chunk);
        }
    }
}
