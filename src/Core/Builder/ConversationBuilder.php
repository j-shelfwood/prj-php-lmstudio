<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Builder;

use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Conversation\Conversation;
use Shelfwood\LMStudio\Core\Event\EventHandler;
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
     * Register a callback for when a tool is called.
     *
     * @param  callable  $callback  The callback function
     */
    public function onToolCall(callable $callback): self
    {
        $this->eventHandler->registerCallback('tool_call', $callback);

        return $this;
    }

    /**
     * Register a callback for when a response is received.
     *
     * @param  callable  $callback  The callback function
     */
    public function onResponse(callable $callback): self
    {
        $this->eventHandler->registerCallback('response', $callback);

        return $this;
    }

    /**
     * Register a callback for when an error occurs.
     *
     * @param  callable  $callback  The callback function
     */
    public function onError(callable $callback): self
    {
        $this->eventHandler->registerCallback('error', $callback);

        return $this;
    }

    /**
     * Register a callback for when a chunk is received during streaming.
     *
     * @param  callable  $callback  The callback function
     */
    public function onChunk(callable $callback): self
    {
        $this->eventHandler->registerCallback('chunk', $callback);

        return $this;
    }

    /**
     * Build the conversation.
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
            $this->streaming
        );
    }
}
