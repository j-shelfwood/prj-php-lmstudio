<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Streaming;

// Updated imports
use Shelfwood\LMStudio\Api\Model\ChatCompletionChunk;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCallDelta;
use Shelfwood\LMStudio\Core\Event\EventHandler;

class StreamingHandler
{
    private readonly EventHandler $eventHandler;

    /**
     * @var array<int, array{id: ?string, type: ?string, function_name: string, function_arguments: string}> Accumulator for tool call fragments, indexed by tool call index
     */
    private array $currentToolCallFragments = [];

    public function __construct()
    {
        $this->eventHandler = new EventHandler;
    }

    /**
     * Handle a parsed streaming chunk.
     *
     * @param  ChatCompletionChunk  $chunk  The parsed chunk object
     */
    public function handleChunk(ChatCompletionChunk $chunk): void
    {
        // Trigger start event on first chunk
        if (! $this->eventHandler->hasBeenTriggered('stream_start')) {
            $this->eventHandler->trigger('stream_start', $chunk);
        }

        try {
            // We expect only one choice in most streaming scenarios
            $choice = $chunk->choices[0] ?? null;

            if (! $choice) {
                return; // Or trigger a warning/error?
            }

            $delta = $choice->delta;

            // Handle content
            if ($delta->content !== null) {
                $this->eventHandler->trigger('stream_content', $delta->content, $chunk);
            }

            // Handle tool calls fragments
            if ($delta->toolCalls !== null) {
                $this->processToolCallDeltas($delta->toolCalls, $chunk);
            }

            // Handle completion (check finish_reason on the choice)
            if ($choice->finishReason !== null) {
                // Assemble final tool calls before triggering stream_end
                $finalToolCalls = $this->assembleCompleteToolCalls();
                $this->eventHandler->trigger('stream_end', $finalToolCalls, $chunk);
                $this->reset();
            }
        } catch (\Exception $e) {
            $this->eventHandler->trigger('stream_error', $e, $chunk);
            $this->reset(); // Reset state on error

            throw $e; // Re-throw after triggering event
        }
    }

    /**
     * Process tool call deltas from a chunk.
     *
     * @param  ToolCallDelta[]  $toolCallDeltas  The tool call deltas from the parsed chunk
     * @param  ChatCompletionChunk  $parentChunk  The parent chunk for context
     */
    private function processToolCallDeltas(array $toolCallDeltas, ChatCompletionChunk $parentChunk): void
    {
        foreach ($toolCallDeltas as $delta) {
            $index = $delta->index;

            // Initialize accumulator for this index if it's the first fragment
            if (! isset($this->currentToolCallFragments[$index])) {
                $this->currentToolCallFragments[$index] = [
                    'id' => $delta->id, // ID usually comes first
                    'type' => $delta->type, // Type usually comes first
                    'function_name' => '',
                    'function_arguments' => '',
                ];
                $this->eventHandler->trigger('stream_tool_call_start', $index, $delta->id, $delta->type, $parentChunk);
            }

            // Accumulate fragments
            if ($delta->functionName !== null) {
                $this->currentToolCallFragments[$index]['function_name'] .= $delta->functionName;
            }

            if ($delta->functionArguments !== null) {
                $this->currentToolCallFragments[$index]['function_arguments'] .= $delta->functionArguments;
            }

            // Emit tool call delta event with the specific fragment
            $this->eventHandler->trigger('stream_tool_call_delta', $index, $delta, $parentChunk);
        }
    }

    /**
     * Assembles complete ToolCall objects from accumulated fragments.
     *
     * @return ToolCall[] Array of fully assembled ToolCall objects.
     */
    private function assembleCompleteToolCalls(): array
    {
        $completeToolCalls = [];

        foreach ($this->currentToolCallFragments as $index => $fragments) {
            try {
                // Use ToolCall::fromArray structure, but construct manually from fragments
                // We assume arguments are valid JSON string by now.
                $argumentsArray = json_decode($fragments['function_arguments'], true, 512, JSON_THROW_ON_ERROR);

                $toolCall = new ToolCall(
                    id: $fragments['id'] ?? uniqid('tool_'.$index.'_'), // Generate ID if missing
                    name: $fragments['function_name'],
                    arguments: $argumentsArray
                );
                $completeToolCalls[] = $toolCall;

                $this->eventHandler->trigger('stream_tool_call_end', $index, $toolCall);

            } catch (\JsonException|\InvalidArgumentException $e) {
                // If assembly fails (e.g., invalid args JSON), trigger an error and skip this tool call
                $this->eventHandler->trigger('stream_tool_call_assembly_error', $index, $fragments, $e);
            }
        }

        return $completeToolCalls;
    }

    /**
     * Get the current *fragments* of tool calls being built.
     * Note: For complete calls, listen for the 'stream_end' event.
     */
    public function getCurrentToolCallFragments(): array
    {
        return $this->currentToolCallFragments;
    }

    /**
     * Reset the handler state.
     */
    public function reset(): self
    {
        $this->currentToolCallFragments = [];
        $this->eventHandler->resetTriggeredEvents();

        return $this;
    }

    /**
     * Register an event handler.
     *
     * @param  string  $event  The event name (e.g., 'stream_content', 'stream_end')
     * @param  callable(mixed...): void  $callback  The handler function
     * @return $this
     */
    public function on(string $event, callable $callback): self
    {
        $this->eventHandler->on($event, $callback);

        return $this;
    }
}
