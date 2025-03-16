<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Streaming;

class StreamingHandler
{
    /**
     * @var callable|null Callback for when streaming starts
     */
    protected $onStart = null;

    /**
     * @var callable|null Callback for when content is received
     */
    protected $onContent = null;

    /**
     * @var callable|null Callback for when a tool call is received
     */
    protected $onToolCall = null;

    /**
     * @var callable|null Callback for when streaming ends
     */
    protected $onEnd = null;

    /**
     * @var callable|null Callback for when an error occurs
     */
    protected $onError = null;

    /**
     * @var string Buffer for accumulated content
     */
    protected $buffer = '';

    /**
     * @var array Accumulated tool calls
     */
    protected $toolCalls = [];

    /**
     * @var array Current tool call being built
     */
    protected $currentToolCall = null;

    /**
     * @var bool Whether streaming has started
     */
    protected $started = false;

    /**
     * Set callback for when streaming starts.
     *
     * @param  callable  $callback  Function to call when streaming starts
     */
    public function onStart(callable $callback): self
    {
        $this->onStart = $callback;

        return $this;
    }

    /**
     * Set callback for when content is received.
     *
     * @param  callable  $callback  Function to call when content is received
     */
    public function onContent(callable $callback): self
    {
        $this->onContent = $callback;

        return $this;
    }

    /**
     * Set callback for when a tool call is received.
     *
     * @param  callable  $callback  Function to call when a tool call is received
     */
    public function onToolCall(callable $callback): self
    {
        $this->onToolCall = $callback;

        return $this;
    }

    /**
     * Set callback for when streaming ends.
     *
     * @param  callable  $callback  Function to call when streaming ends
     */
    public function onEnd(callable $callback): self
    {
        $this->onEnd = $callback;

        return $this;
    }

    /**
     * Set callback for when an error occurs.
     *
     * @param  callable  $callback  Function to call when an error occurs
     */
    public function onError(callable $callback): self
    {
        $this->onError = $callback;

        return $this;
    }

    /**
     * Handle a streaming chunk.
     *
     * @param  array  $chunk  The chunk data
     */
    public function handleChunk(array $chunk): void
    {
        // Check if this is the first chunk
        if (! $this->started) {
            $this->started = true;

            if ($this->onStart) {
                call_user_func($this->onStart);
            }
        }

        // Process content
        if (isset($chunk['choices'][0]['delta']['content'])) {
            $content = $chunk['choices'][0]['delta']['content'];
            $this->buffer .= $content;

            if ($this->onContent) {
                call_user_func($this->onContent, $content, $this->buffer, $this->isComplete($chunk));
            }
        }

        // Process tool calls
        if (isset($chunk['choices'][0]['delta']['tool_calls'])) {
            $this->processToolCalls($chunk, $chunk['choices'][0]['delta']['tool_calls']);
        }

        // Check if complete
        if ($this->isComplete($chunk) && $this->onEnd) {
            call_user_func($this->onEnd, $this->buffer, $this->toolCalls);
        }
    }

    /**
     * Handle an error during streaming.
     *
     * @param  \Throwable  $error  The error that occurred
     */
    public function handleError(\Throwable $error): void
    {
        if ($this->onError) {
            call_user_func($this->onError, $error, $this->buffer, $this->toolCalls);
        }
    }

    /**
     * Process tool call deltas from a chunk.
     *
     * @param  array  $chunk  The full chunk data
     * @param  array  $toolCallDeltas  The tool call deltas
     */
    protected function processToolCalls(array $chunk, array $toolCallDeltas): void
    {
        foreach ($toolCallDeltas as $index => $delta) {
            // Initialize tool call if it doesn't exist
            if (! isset($this->toolCalls[$index])) {
                $this->toolCalls[$index] = [
                    'id' => $delta['id'] ?? '',
                    'type' => $delta['type'] ?? 'function',
                    'function' => [
                        'name' => $delta['function']['name'] ?? '',
                        'arguments' => $delta['function']['arguments'] ?? '',
                    ],
                ];
            } else {
                $currentToolCall = &$this->toolCalls[$index]['function'];

                if (isset($delta['function']['name'])) {
                    $currentToolCall['name'] .= $delta['function']['name'];
                }

                if (isset($delta['function']['arguments'])) {
                    $currentToolCall['arguments'] .= $delta['function']['arguments'];
                }
            }

            // Only process the tool call if this is the final chunk
            if ($this->isToolCallComplete($chunk)) {
                try {
                    $currentToolCall = &$this->toolCalls[$index]['function'];

                    // Try to parse arguments as JSON if they're a string
                    if (isset($currentToolCall['arguments']) && is_string($currentToolCall['arguments'])) {
                        try {
                            $parsedArguments = json_decode($currentToolCall['arguments'], true, 512, JSON_THROW_ON_ERROR);

                            if (is_array($parsedArguments)) {
                                $currentToolCall['arguments'] = $parsedArguments;
                            }
                        } catch (\JsonException $e) {
                            error_log(sprintf(
                                '[WARNING] StreamingHandler: Invalid JSON arguments for tool call index %d: %s. Arguments: %s',
                                $index,
                                $e->getMessage(),
                                $currentToolCall['arguments']
                            ));

                            continue;
                        }
                    }

                    // Only trigger callback for complete and valid tool calls
                    if ($this->onToolCall &&
                        isset($currentToolCall['name']) &&
                        ! empty($currentToolCall['name']) &&
                        isset($currentToolCall['arguments'])) {

                        call_user_func(
                            $this->onToolCall,
                            $this->toolCalls[$index],
                            $index,
                            true
                        );
                    }
                } catch (\Throwable $e) {
                    error_log(sprintf(
                        '[ERROR] StreamingHandler: Error processing tool call: %s. Tool call data: %s',
                        $e->getMessage(),
                        json_encode($this->toolCalls[$index])
                    ));
                }
            }
        }
    }

    /**
     * Check if a chunk indicates a complete tool call.
     *
     * @param  array  $chunk  The chunk data
     * @return bool Whether the tool call is complete
     */
    protected function isToolCallComplete(array $chunk): bool
    {
        return isset($chunk['choices'][0]['finish_reason']) && $chunk['choices'][0]['finish_reason'] === 'tool_calls'
            || isset($chunk['choices'][0]['delta']['tool_calls'][0]['function']['arguments']);
    }

    /**
     * Check if a chunk indicates the completion of streaming.
     *
     * @param  array  $chunk  The chunk data
     * @return bool Whether streaming is complete
     */
    protected function isComplete(array $chunk): bool
    {
        return isset($chunk['choices'][0]['finish_reason']) &&
               $chunk['choices'][0]['finish_reason'] !== null;
    }

    /**
     * Get the accumulated content buffer.
     *
     * @return string The content buffer
     */
    public function getBuffer(): string
    {
        return $this->buffer;
    }

    /**
     * Get the accumulated tool calls.
     *
     * @return array The tool calls
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * Reset the handler state.
     */
    public function reset(): self
    {
        $this->buffer = '';
        $this->resetToolCalls();
        $this->started = false;

        return $this;
    }

    /**
     * Reset the accumulated tool calls.
     */
    public function resetToolCalls(): self
    {
        $this->toolCalls = [];
        $this->started = false;

        return $this;
    }
}
