<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Builder;

use Shelfwood\LMStudio\Api\Model\ChatCompletionChunk;
use Shelfwood\LMStudio\Api\Model\ResponseFormat;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCallDelta;
use Shelfwood\LMStudio\Api\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Conversation\Conversation;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Throwable;

/**
 * Builder for creating and configuring Conversation instances.
 */
class ConversationBuilder
{
    private readonly ChatService $chatService;

    private string $model;

    /** @var array<string, mixed> */
    private array $options = [];

    private ToolRegistry $toolRegistry;

    private EventHandler $eventHandler;

    private bool $streaming = false;

    private ?StreamingHandler $streamingHandler = null;

    private ?ToolExecutor $toolExecutor = null;

    /**
     * Create a new ConversationBuilder.
     *
     * @param  ChatService  $chatService  The chat service
     * @param  string  $model  The model to use
     * @param  ToolRegistry|null  $toolRegistry  Optional ToolRegistry instance
     * @param  EventHandler|null  $eventHandler  Optional EventHandler instance
     */
    public function __construct(
        ChatService $chatService,
        string $model,
        ?ToolRegistry $toolRegistry = null,
        ?EventHandler $eventHandler = null
    ) {
        $this->chatService = $chatService;
        $this->model = $model;
        // Use provided instances or create new ones
        $this->toolRegistry = $toolRegistry ?? new ToolRegistry;
        $this->eventHandler = $eventHandler ?? new EventHandler;
        // Ensure ToolExecutor uses the correct registry and handler
        $this->toolExecutor = new ToolExecutor($this->toolRegistry, $this->eventHandler);
    }

    /**
     * Set the model to use.
     *
     * @param  string  $model  The model to use
     */
    public function withModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set additional options.
     *
     * @param  array<string, mixed>  $options  Additional options
     */
    public function withOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * Register a tool function.
     *
     * @param  string  $name  The name of the function
     * @param  callable(array<string, mixed>): mixed  $callback  The function to call
     * @param  array<string, mixed>  $parameters  The function parameters schema
     * @param  string|null  $description  The function description
     */
    public function withTool(string $name, callable $callback, array $parameters, ?string $description = null): self
    {
        $this->toolRegistry->registerTool($name, $callback, $parameters, $description);

        return $this;
    }

    /**
     * Enable streaming for the conversation.
     *
     * @param  bool  $streaming  Whether to enable streaming
     */
    public function withStreaming(bool $streaming = true): self
    {
        $this->streaming = $streaming;
        $this->options['stream'] = $streaming;

        return $this;
    }

    /**
     * Set a streaming handler for the conversation.
     *
     * @param  StreamingHandler  $handler  The streaming handler
     */
    public function withStreamingHandler(StreamingHandler $handler): self
    {
        $this->streamingHandler = $handler;
        $this->withStreaming(true);

        return $this;
    }

    /**
     * Set a tool registry for the conversation.
     *
     * @param  ToolRegistry  $registry  The tool registry
     */
    public function withToolRegistry(ToolRegistry $registry): self
    {
        $this->toolRegistry = $registry;

        return $this;
    }

    /**
     * Set a tool executor for the conversation.
     *
     * @param  ToolExecutor  $executor  The tool executor
     */
    public function withToolExecutor(ToolExecutor $executor): self
    {
        $this->toolExecutor = $executor;

        return $this;
    }

    /**
     * Register a callback for when a tool is called.
     *
     * @param  callable(string, array<string, mixed>, string): void  $callback  (string $toolName, array $arguments, string $toolCallId)
     */
    public function onToolCall(callable $callback): self
    {
        $this->eventHandler->on('tool.executing', $callback);

        return $this;
    }

    /**
     * Register a callback for when a tool is executed.
     *
     * @param  callable(string, array<string, mixed>, string, mixed): void  $callback  (string $toolName, array $arguments, string $toolCallId, mixed $result)
     */
    public function onToolExecuted(callable $callback): self
    {
        $this->eventHandler->on('tool.executed', $callback);

        return $this;
    }

    /**
     * Register a callback for when a response is received (non-streaming).
     *
     * @param  callable(ChatCompletionResponse): void  $callback
     */
    public function onResponse(callable $callback): self
    {
        $this->eventHandler->on('response', $callback);

        return $this;
    }

    /**
     * Register a callback for when an error occurs.
     *
     * @param  callable(Throwable): void  $callback
     */
    public function onError(callable $callback): self
    {
        $this->eventHandler->on('error', $callback);

        return $this;
    }

    /**
     * Register a callback for when a chunk is received during streaming.
     *
     * @param  callable(ChatCompletionChunk): void  $callback
     */
    public function onChunk(callable $callback): self
    {
        $this->eventHandler->on('chunk', $callback);

        return $this;
    }

    /**
     * Register a callback for when streaming starts.
     *
     * @param  callable(ChatCompletionChunk): void  $callback
     */
    public function onStreamStart(callable $callback): self
    {
        if ($this->streamingHandler === null) {
            $this->streamingHandler = new StreamingHandler;
            $this->withStreaming(true);
        }

        $this->streamingHandler->on('stream_start', $callback);

        return $this;
    }

    /**
     * Register a callback for when content is received during streaming.
     *
     * @param  callable(string, ChatCompletionChunk): void  $callback  (string $content, ChatCompletionChunk $chunk)
     */
    public function onStreamContent(callable $callback): self
    {
        if ($this->streamingHandler === null) {
            $this->streamingHandler = new StreamingHandler;
            $this->withStreaming(true);
        }

        $this->streamingHandler->on('stream_content', $callback);

        return $this;
    }

    /**
     * Register a callback for when a tool call START is received during streaming.
     *
     * @param  callable(int, ?string, ?string, ChatCompletionChunk): void  $callback  (int $index, ?string $id, ?string $type, ChatCompletionChunk $chunk)
     */
    public function onStreamToolCallStart(callable $callback): self
    {
        if ($this->streamingHandler === null) {
            $this->streamingHandler = new StreamingHandler($this->eventHandler);
            $this->withStreaming(true);
        }
        $this->streamingHandler->on('stream_tool_call_start', $callback);

        return $this;
    }

    /**
     * Register a callback for when a tool call DELTA is received during streaming.
     *
     * @param  callable(int, ToolCallDelta, ChatCompletionChunk): void  $callback  (int $index, ToolCallDelta $delta, ChatCompletionChunk $chunk)
     */
    public function onStreamToolCallDelta(callable $callback): self
    {
        if ($this->streamingHandler === null) {
            $this->streamingHandler = new StreamingHandler($this->eventHandler);
            $this->withStreaming(true);
        }
        $this->streamingHandler->on('stream_tool_call_delta', $callback);

        return $this;
    }

    /**
     * Register a callback for when a tool call END (assembly complete/failed) is received during streaming.
     *
     * @param  callable(int, ToolCall): void  $callback  (int $index, ToolCall $assembledToolCall)
     */
    public function onStreamToolCallEnd(callable $callback): self
    {
        if ($this->streamingHandler === null) {
            $this->streamingHandler = new StreamingHandler($this->eventHandler);
            $this->withStreaming(true);
        }
        $this->streamingHandler->on('stream_tool_call_end', $callback);

        return $this;
    }

    /**
     * Register a callback for when streaming ends.
     *
     * @param  callable(?array<ToolCall>, ChatCompletionChunk): void  $callback  (?array $finalToolCalls, ChatCompletionChunk $chunk)
     */
    public function onStreamEnd(callable $callback): self
    {
        if ($this->streamingHandler === null) {
            $this->streamingHandler = new StreamingHandler;
            $this->withStreaming(true);
        }

        $this->streamingHandler->on('stream_end', $callback);

        return $this;
    }

    /**
     * Register a callback for when a streaming error occurs.
     *
     * @param  callable(Throwable, ?ChatCompletionChunk): void  $callback  (Throwable $error, ?ChatCompletionChunk $chunk)
     */
    public function onStreamError(callable $callback): self
    {
        if ($this->streamingHandler === null) {
            $this->streamingHandler = new StreamingHandler;
            $this->withStreaming(true);
        }

        $this->streamingHandler->on('stream_error', $callback);

        return $this;
    }

    /**
     * Set the response format.
     *
     * @param  ResponseFormat  $responseFormat  The response format
     */
    public function withResponseFormat(ResponseFormat $responseFormat): self
    {
        $this->options['response_format'] = $responseFormat;

        return $this;
    }

    /**
     * Get the tool registry.
     *
     * @return ToolRegistry The tool registry
     */
    public function getToolRegistry(): ToolRegistry
    {
        return $this->toolRegistry;
    }

    /**
     * Add a system message to the conversation.
     *
     * @param  string  $content  The system message content
     */
    public function withSystemMessage(string $content): self
    {
        $this->eventHandler->on('conversation_build', function (Conversation $conversation) use ($content): void {
            $conversation->addSystemMessage($content);
        });

        return $this;
    }

    /**
     * Build the conversation.
     */
    public function build(): Conversation
    {
        // We no longer add tools to options here
        // Tools will be handled directly by the Conversation class
        // This avoids duplication issues with the tools array

        return new Conversation(
            $this->chatService,
            $this->model,
            $this->options,
            $this->toolRegistry,
            $this->eventHandler,
            $this->streaming,
            $this->streamingHandler,
            $this->toolExecutor
        );
    }
}
