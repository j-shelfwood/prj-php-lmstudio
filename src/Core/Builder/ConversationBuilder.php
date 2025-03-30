<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Builder;

use Shelfwood\LMStudio\Api\Model\ResponseFormat;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Conversation\Conversation;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;

/**
 * Builder for creating and configuring Conversation instances.
 */
class ConversationBuilder
{
    private ChatService $chatService;

    private string $model;

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
     */
    public function __construct(ChatService $chatService, string $model)
    {
        $this->chatService = $chatService;
        $this->model = $model;
        $this->toolRegistry = new ToolRegistry;
        $this->eventHandler = new EventHandler;
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
     * @param  array  $options  Additional options
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
     * @param  callable  $callback  The function to call
     * @param  array  $parameters  The function parameters schema
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
     * @param  callable  $callback  The callback function
     */
    public function onToolCall(callable $callback): self
    {
        $this->eventHandler->on('tool_call', $callback);

        return $this;
    }

    /**
     * Register a callback for when a tool is executed.
     *
     * @param  callable  $callback  The callback function
     */
    public function onToolExecuted(callable $callback): self
    {
        $this->eventHandler->on('tool_executed', $callback);

        return $this;
    }

    /**
     * Register a callback for when a response is received.
     *
     * @param  callable  $callback  The callback function
     */
    public function onResponse(callable $callback): self
    {
        $this->eventHandler->on('response', $callback);

        return $this;
    }

    /**
     * Register a callback for when an error occurs.
     *
     * @param  callable  $callback  The callback function
     */
    public function onError(callable $callback): self
    {
        $this->eventHandler->on('error', $callback);

        return $this;
    }

    /**
     * Register a callback for when a chunk is received during streaming.
     *
     * @param  callable  $callback  The callback function
     */
    public function onChunk(callable $callback): self
    {
        $this->eventHandler->on('chunk', $callback);

        return $this;
    }

    /**
     * Register a callback for when streaming starts.
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
     * Register a callback for when a tool call is received during streaming.
     */
    public function onStreamToolCall(callable $callback): self
    {
        if ($this->streamingHandler === null) {
            $this->streamingHandler = new StreamingHandler;
            $this->withStreaming(true);
        }

        $this->streamingHandler->on('stream_tool_call', function ($data) use ($callback): void {
            $callback($data['tool_call'], $data['index']);
        });

        return $this;
    }

    /**
     * Register a callback for when streaming ends.
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
     * Register a callback for when a tool call delta is received during streaming.
     */
    public function onToolCallDelta(callable $callback): self
    {
        if ($this->streamingHandler === null) {
            $this->streamingHandler = new StreamingHandler;
            $this->withStreaming(true);
        }

        $this->streamingHandler->on('stream_tool_call', function ($data) use ($callback): void {
            $callback($data['tool_call'], $data['index']);
        });

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
