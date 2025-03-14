<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Builder;

use Shelfwood\LMStudio\Api\Model\ResponseFormat;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Conversation\Conversation;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\Core\Tools\ToolExecutionHandler;

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

    private ?ToolExecutionHandler $toolExecutionHandler = null;

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
     * Set a tool execution handler for the conversation.
     *
     * @param  ToolExecutionHandler  $handler  The tool execution handler
     */
    public function withToolExecutionHandler(ToolExecutionHandler $handler): self
    {
        $this->toolExecutionHandler = $handler;

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
     *
     * @param  callable  $callback  The callback function
     */
    public function onStreamStart(callable $callback): self
    {
        if ($this->streamingHandler === null) {
            $this->streamingHandler = new StreamingHandler;
            $this->withStreaming(true);
        }

        $this->streamingHandler->onStart($callback);

        return $this;
    }

    /**
     * Register a callback for when content is received during streaming.
     *
     * @param  callable  $callback  The callback function
     */
    public function onStreamContent(callable $callback): self
    {
        if ($this->streamingHandler === null) {
            $this->streamingHandler = new StreamingHandler;
            $this->withStreaming(true);
        }

        $this->streamingHandler->onContent($callback);

        return $this;
    }

    /**
     * Register a callback for when a tool call is received during streaming.
     *
     * @param  callable  $callback  The callback function
     */
    public function onStreamToolCall(callable $callback): self
    {
        if ($this->streamingHandler === null) {
            $this->streamingHandler = new StreamingHandler;
            $this->withStreaming(true);
        }

        $this->streamingHandler->onToolCall($callback);

        return $this;
    }

    /**
     * Register a callback for when streaming ends.
     *
     * @param  callable  $callback  The callback function
     */
    public function onStreamEnd(callable $callback): self
    {
        if ($this->streamingHandler === null) {
            $this->streamingHandler = new StreamingHandler;
            $this->withStreaming(true);
        }

        $this->streamingHandler->onEnd($callback);

        return $this;
    }

    /**
     * Register a callback for when a streaming error occurs.
     *
     * @param  callable  $callback  The callback function
     */
    public function onStreamError(callable $callback): self
    {
        if ($this->streamingHandler === null) {
            $this->streamingHandler = new StreamingHandler;
            $this->withStreaming(true);
        }

        $this->streamingHandler->onError($callback);

        return $this;
    }

    /**
     * Register a callback for when a tool call is received.
     *
     * @param  callable  $callback  The callback function
     */
    public function onToolReceived(callable $callback): self
    {
        if ($this->toolExecutionHandler === null) {
            $this->toolExecutionHandler = new ToolExecutionHandler;
        }

        $this->toolExecutionHandler->onReceived($callback);

        return $this;
    }

    /**
     * Register a callback for when a tool is about to be executed.
     *
     * @param  callable  $callback  The callback function
     */
    public function onToolExecuting(callable $callback): self
    {
        if ($this->toolExecutionHandler === null) {
            $this->toolExecutionHandler = new ToolExecutionHandler;
        }

        $this->toolExecutionHandler->onExecuting($callback);

        return $this;
    }

    /**
     * Register a callback for when a tool has been executed successfully.
     *
     * @param  callable  $callback  The callback function
     */
    public function onToolExecuted(callable $callback): self
    {
        if ($this->toolExecutionHandler === null) {
            $this->toolExecutionHandler = new ToolExecutionHandler;
        }

        $this->toolExecutionHandler->onExecuted($callback);

        return $this;
    }

    /**
     * Register a callback for when a tool execution fails.
     *
     * @param  callable  $callback  The callback function
     */
    public function onToolError(callable $callback): self
    {
        if ($this->toolExecutionHandler === null) {
            $this->toolExecutionHandler = new ToolExecutionHandler;
        }

        $this->toolExecutionHandler->onError($callback);

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
     * Build the conversation.
     *
     * @return Conversation The conversation instance
     */
    public function build(): Conversation
    {
        // Add tools to options if any are registered
        if ($this->toolRegistry->hasTools()) {
            $this->options['tools'] = $this->toolRegistry->getToolsArray();
        }

        return new Conversation(
            $this->chatService,
            $this->model,
            $this->options,
            $this->toolRegistry,
            $this->eventHandler,
            $this->streaming,
            $this->streamingHandler,
            $this->toolExecutionHandler
        );
    }
}
