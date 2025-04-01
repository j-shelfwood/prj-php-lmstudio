<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Manager;

use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Core\Conversation\ConversationState;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Turn\NonStreamingTurnHandler;
use Shelfwood\LMStudio\Core\Turn\StreamingTurnHandler;
use Throwable;

/**
 * Provides the primary user interface for interacting with a conversation
 * after it has been configured and built. Manages conversation state and
 * delegates turn handling (request/response or streaming) to specialized handlers.
 */
class ConversationManager
{
    /**
     * @param  ConversationState  $state  The state object holding messages, model, and options.
     * @param  NonStreamingTurnHandler  $nonStreamingHandler  Handler for simple request/response turns.
     * @param  StreamingTurnHandler|null  $streamingTurnHandler  Handler for streaming turns (null if streaming not enabled).
     * @param  EventHandler  $eventHandler  Handler for general events (tool execution, errors, responses).
     * @param  StreamingHandler|null  $streamProcessor  Handler for processing stream chunks and emitting stream-specific events (null if streaming not enabled).
     * @param  bool  $isStreaming  Flag indicating if this conversation is configured for streaming.
     */
    public function __construct(
        public readonly ConversationState $state,
        private readonly NonStreamingTurnHandler $nonStreamingHandler,
        private readonly ?StreamingTurnHandler $streamingTurnHandler,
        private readonly EventHandler $eventHandler,
        private readonly ?StreamingHandler $streamProcessor,
        public readonly bool $isStreaming
    ) {}

    /**
     * Executes a non-streaming turn, sending the current messages and handling potential tool calls.
     *
     * @param  int|null  $timeout  Optional timeout (Note: Primarily managed by HttpClient configuration for non-streaming).
     * @return string The final assistant message content for the turn.
     *
     * @throws \RuntimeException If the conversation was configured for streaming.
     * @throws \Throwable For API errors, tool execution errors, etc.
     */
    public function getResponse(?int $timeout = null): string
    {
        if ($this->isStreaming) {
            throw new \RuntimeException('Cannot call getResponse() on a streaming conversation. Use handleStreamingTurn() instead.');
        }

        // NonStreamingTurnHandler::handle already triggers error events
        return $this->nonStreamingHandler->handle($this->state, $timeout);
    }

    /**
     * Executes a full streaming turn, including initiating the stream, processing chunks,
     * handling potential tool calls after the stream ends, and returning the final content.
     *
     * @param  int|null  $timeout  Optional timeout in seconds for the entire turn.
     * @return string The final assistant message content after the stream and any tool calls are processed.
     *
     * @throws \RuntimeException If the conversation was not configured for streaming or the necessary handler is missing.
     * @throws \Shelfwood\LMStudio\Core\Exception\TurnTimeoutException If the turn exceeds the timeout.
     * @throws \Throwable For API errors, tool execution errors, etc.
     */
    public function handleStreamingTurn(?int $timeout = null): string
    {
        if (! $this->isStreaming) {
            throw new \RuntimeException('Cannot call handleStreamingTurn() on a non-streaming conversation. Use getResponse() instead.');
        }

        if ($this->streamingTurnHandler === null) {
            // This should ideally not happen if the builder logic is correct
            throw new \RuntimeException('StreamingTurnHandler is missing for a streaming conversation.');
        }

        // StreamingTurnHandler::handle already triggers error events
        return $this->streamingTurnHandler->handle($this->state, $timeout);
    }

    /**
     * Adds a message object directly to the conversation history.
     *
     * @param  Message  $message  The message object to add.
     * @return $this
     */
    public function addMessage(Message $message): self
    {
        $this->state->addMessage($message);

        return $this;
    }

    /**
     * Adds a user message to the conversation history.
     *
     * @param  string  $content  The text content of the user message.
     * @return $this
     */
    public function addUserMessage(string $content): self
    {
        $this->state->addUserMessage($content);

        return $this;
    }

    /**
     * Adds a system message to the conversation history.
     *
     * @param  string  $content  The text content of the system message.
     * @return $this
     */
    public function addSystemMessage(string $content): self
    {
        $this->state->addSystemMessage($content);

        return $this;
    }

    /**
     * Adds an assistant message to the conversation history.
     * Typically used internally or when reconstructing state.
     *
     * @param  string  $content  The text content of the assistant message.
     * @param  list<\Shelfwood\LMStudio\Api\Model\Tool\ToolCall>|null  $toolCalls  Optional tool calls.
     * @return $this
     */
    public function addAssistantMessage(string $content, ?array $toolCalls = null): self
    {
        $this->state->addAssistantMessage($content, $toolCalls);

        return $this;
    }

    /**
     * Adds a tool result message to the conversation history.
     * Typically used internally or when reconstructing state.
     *
     * @param  string  $toolCallId  The ID of the tool call this is a result for.
     * @param  string  $content  The result content.
     * @return $this
     */
    public function addToolMessage(string $toolCallId, string $content): self
    {
        $this->state->addToolMessage($toolCallId, $content);

        return $this;
    }

    /**
     * Retrieves the complete message history.
     *
     * @return list<Message>
     */
    public function getMessages(): array
    {
        return $this->state->getMessages();
    }

    /**
     * Clears all messages from the conversation history.
     *
     * @return $this
     */
    public function clearMessages(): self
    {
        $this->state->clearMessages();

        return $this;
    }

    /**
     * Gets the model ID configured for this conversation.
     */
    public function getModel(): string
    {
        return $this->state->getModel();
    }

    /**
     * Gets the initial options configured for this conversation.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->state->getOptions();
    }

    // --- Event Passthrough Methods ---

    /**
     * Register a callback for when a non-streaming response is received.
     * The callback receives the raw ChatCompletionResponse object.
     *
     * @param  callable(\Shelfwood\LMStudio\Api\Response\ChatCompletionResponse): void  $callback
     * @return $this
     */
    public function onResponse(callable $callback): self
    {
        $this->eventHandler->on('response', $callback);

        return $this;
    }

    /**
     * Register a callback for when any error occurs during a turn.
     * The callback receives the Throwable error/exception object.
     *
     * @param  callable(Throwable): void  $callback
     * @return $this
     */
    public function onError(callable $callback): self
    {
        $this->eventHandler->on('error', $callback);

        return $this;
    }

    /**
     * Register a callback for before a tool starts executing.
     * Receives tool name, arguments array, and tool call ID.
     *
     * @param  callable(string, array<string, mixed>, string): void  $callback
     * @return $this
     */
    public function onToolExecuting(callable $callback): self
    {
        $this->eventHandler->on('tool.executing', $callback);

        return $this;
    }

    /**
     * Register a callback for after a tool has successfully executed.
     * Receives tool name, arguments array, tool call ID, and the execution result.
     *
     * @param  callable(string, array<string, mixed>, string, mixed): void  $callback
     * @return $this
     */
    public function onToolExecuted(callable $callback): self
    {
        $this->eventHandler->on('tool.executed', $callback);

        return $this;
    }

    /**
     * Register a callback for when a tool execution fails.
     * Receives tool name, arguments array, tool call ID, and the Throwable error.
     *
     * @param  callable(string, array<string, mixed>, string, Throwable): void  $callback
     * @return $this
     */
    public function onToolError(callable $callback): self
    {
        $this->eventHandler->on('tool.error', $callback);

        return $this;
    }

    // --- Stream Event Passthrough Methods ---

    /**
     * Register a callback for when the stream starts.
     * Requires the conversation to be configured for streaming.
     * Receives the initial ChatCompletionChunk.
     *
     * @param  callable(\Shelfwood\LMStudio\Api\Model\ChatCompletionChunk): void  $callback
     * @return $this
     *
     * @throws \RuntimeException if not a streaming conversation.
     */
    public function onStreamStart(callable $callback): self
    {
        $this->ensureStreamingHandler();
        $this->streamProcessor->on('stream_start', $callback);

        return $this;
    }

    /**
     * Register a callback for receiving content chunks during streaming.
     * Requires the conversation to be configured for streaming.
     * Receives the string content delta and the corresponding ChatCompletionChunk.
     *
     * @param  callable(string, \Shelfwood\LMStudio\Api\Model\ChatCompletionChunk): void  $callback
     * @return $this
     *
     * @throws \RuntimeException if not a streaming conversation.
     */
    public function onStreamContent(callable $callback): self
    {
        $this->ensureStreamingHandler();
        $this->streamProcessor->on('stream_content', $callback);

        return $this;
    }

    /**
     * Register a callback for receiving the start of a tool call during streaming.
     * Requires the conversation to be configured for streaming.
     * Receives the ToolCallDelta and the corresponding ChatCompletionChunk.
     *
     * @param  callable(\Shelfwood\LMStudio\Api\Model\Tool\ToolCallDelta, \Shelfwood\LMStudio\Api\Model\ChatCompletionChunk): void  $callback
     * @return $this
     *
     * @throws \RuntimeException if not a streaming conversation.
     */
    public function onStreamToolCallStart(callable $callback): self
    {
        $this->ensureStreamingHandler();
        $this->streamProcessor->on('stream_tool_call_start', $callback);

        return $this;
    }

    /**
     * Register a callback for receiving argument deltas for a tool call during streaming.
     * Requires the conversation to be configured for streaming.
     * Receives the ToolCallDelta (containing argument chunk) and the corresponding ChatCompletionChunk.
     *
     * @param  callable(\Shelfwood\LMStudio\Api\Model\Tool\ToolCallDelta, \Shelfwood\LMStudio\Api\Model\ChatCompletionChunk): void  $callback
     * @return $this
     *
     * @throws \RuntimeException if not a streaming conversation.
     */
    public function onStreamToolCallDelta(callable $callback): self
    {
        $this->ensureStreamingHandler();
        $this->streamProcessor->on('stream_tool_call_delta', $callback);

        return $this;
    }

    /**
     * Register a callback for when a complete tool call is assembled during streaming.
     * Requires the conversation to be configured for streaming.
     * Receives the fully assembled ToolCall object.
     *
     * @param  callable(\Shelfwood\LMStudio\Api\Model\Tool\ToolCall): void  $callback
     * @return $this
     *
     * @throws \RuntimeException if not a streaming conversation.
     */
    public function onStreamToolCallEnd(callable $callback): self
    {
        $this->ensureStreamingHandler();
        $this->streamProcessor->on('stream_tool_call_end', $callback);

        return $this;
    }

    /**
     * Register a callback for when the stream ends.
     * Requires the conversation to be configured for streaming.
     * Receives the final list of completely assembled ToolCall objects (may be empty).
     *
     * @param  callable(list<\Shelfwood\LMStudio\Api\Model\Tool\ToolCall>): void  $callback
     * @return $this
     *
     * @throws \RuntimeException if not a streaming conversation.
     */
    public function onStreamEnd(callable $callback): self
    {
        $this->ensureStreamingHandler();
        $this->streamProcessor->on('stream_end', $callback);

        return $this;
    }

    /**
     * Register a callback for when an error occurs specifically during stream processing.
     * Requires the conversation to be configured for streaming.
     * Receives the Throwable error.
     *
     * Note: General errors (API connection, tool execution post-stream) trigger `onError`.
     *
     * @param  callable(Throwable): void  $callback
     * @return $this
     *
     * @throws \RuntimeException if not a streaming conversation.
     */
    public function onStreamError(callable $callback): self
    {
        $this->ensureStreamingHandler();
        $this->streamProcessor->on('stream_error', $callback);

        return $this;
    }

    /**
     * Helper method to ensure the StreamingHandler is available when needed.
     *
     * @throws \RuntimeException If the conversation is not streaming or the handler is missing.
     */
    private function ensureStreamingHandler(): void
    {
        if (! $this->isStreaming || $this->streamProcessor === null) {
            throw new \RuntimeException('This operation requires a streaming conversation with a configured StreamingHandler.');
        }
    }
}
