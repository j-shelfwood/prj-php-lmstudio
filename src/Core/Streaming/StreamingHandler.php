<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Streaming;

use Shelfwood\LMStudio\Core\Event\EventHandler;

class StreamingHandler
{
    private EventHandler $eventHandler;

    /**
     * @var array Current tool calls being built (indexed by tool call index)
     */
    private array $currentToolCalls = [];

    public function __construct()
    {
        $this->eventHandler = new EventHandler;
    }

    /**
     * Handle a streaming chunk.
     *
     * @param  array  $chunk  The chunk data
     */
    public function handleChunk(array $chunk): void
    {
        // Trigger start event on first chunk
        if (! $this->eventHandler->hasBeenTriggered('stream_start')) {
            $this->eventHandler->trigger('stream_start');
        }

        try {
            // Handle content
            if (isset($chunk['choices'][0]['delta']['content'])) {
                $content = $chunk['choices'][0]['delta']['content'];
                $this->eventHandler->trigger('stream_content', $content);
            }

            // Handle tool calls
            if (isset($chunk['choices'][0]['delta']['tool_calls'])) {
                $this->processToolCalls($chunk['choices'][0]['delta']['tool_calls']);
            }

            // Handle completion
            if ($this->isComplete($chunk)) {
                $this->eventHandler->trigger('stream_end');
                $this->reset();
            }
        } catch (\Exception $e) {
            $this->eventHandler->trigger('stream_error', $e);

            throw $e;
        }
    }

    /**
     * Process tool calls from a chunk.
     *
     * @param  array  $toolCalls  The tool calls data
     */
    private function processToolCalls(array $toolCalls): void
    {
        foreach ($toolCalls as $toolCall) {
            $index = $toolCall['index'];

            // Initialize or update tool call
            if (! isset($this->currentToolCalls[$index])) {
                $this->currentToolCalls[$index] = [
                    'id' => $toolCall['id'] ?? '',
                    'type' => $toolCall['type'] ?? 'function',
                    'function' => [
                        'name' => $toolCall['function']['name'] ?? '',
                        'arguments' => $toolCall['function']['arguments'] ?? '',
                    ],
                ];
            } else {
                if (isset($toolCall['function']['name'])) {
                    $this->currentToolCalls[$index]['function']['name'] .= $toolCall['function']['name'];
                }

                if (isset($toolCall['function']['arguments'])) {
                    $this->currentToolCalls[$index]['function']['arguments'] .= $toolCall['function']['arguments'];
                }
            }

            // Emit tool call delta event
            $this->eventHandler->trigger('stream_tool_call', [
                'tool_call' => $this->currentToolCalls[$index],
                'index' => $index,
            ]);
        }
    }

    /**
     * Check if a chunk indicates the completion of streaming.
     */
    private function isComplete(array $chunk): bool
    {
        return isset($chunk['choices'][0]['finish_reason']) &&
               $chunk['choices'][0]['finish_reason'] !== null;
    }

    /**
     * Get the current tool calls being built.
     */
    public function getCurrentToolCalls(): array
    {
        return $this->currentToolCalls;
    }

    /**
     * Reset the handler state.
     */
    public function reset(): self
    {
        $this->currentToolCalls = [];
        $this->eventHandler->resetTriggeredEvents();

        return $this;
    }

    /**
     * Register an event handler.
     */
    public function on(string $event, callable $callback): self
    {
        $this->eventHandler->on($event, $callback);

        return $this;
    }
}
