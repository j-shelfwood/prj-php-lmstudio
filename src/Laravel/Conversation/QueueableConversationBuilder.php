<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel\Conversation;

use Illuminate\Contracts\Queue\Queue as QueueDispatcherContract;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Builder\ConversationBuilder;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\Laravel\Tools\QueueableToolExecutionHandler;

/**
 * Extends ConversationBuilder to provide queueable tool execution capabilities.
 */
class QueueableConversationBuilder extends ConversationBuilder
{
    /**
     * @var QueueableToolExecutionHandler The queueable tool execution handler
     */
    private QueueableToolExecutionHandler $queueableToolExecutionHandler;

    /**
     * Create a new queueable conversation builder.
     *
     * @param  ChatService  $chatService  The chat service
     * @param  string  $model  The model to use
     * @param  ToolRegistry  $toolRegistry  The tool registry
     * @param  EventHandler  $eventHandler  The event handler
     * @param  QueueDispatcherContract  $queueDispatcher  The queue dispatcher
     * @param  bool|null  $queueToolsByDefault  Whether to queue tool executions by default
     */
    public function __construct(
        ChatService $chatService,
        string $model,
        ToolRegistry $toolRegistry,
        EventHandler $eventHandler,
        QueueDispatcherContract $queueDispatcher,
        ?bool $queueToolsByDefault = false
    ) {
        // Pass core dependencies to the parent constructor
        parent::__construct($chatService, $model, $toolRegistry, $eventHandler);

        // Create the queueable handler with all its dependencies
        $this->queueableToolExecutionHandler = new QueueableToolExecutionHandler(
            $toolRegistry,
            $eventHandler,
            $queueDispatcher,
            $queueToolsByDefault
        );

        // Set the queueable handler as the executor for this builder
        $this->withToolExecutor($this->queueableToolExecutionHandler);
    }

    /**
     * Set whether a specific tool should be queued.
     *
     * @param  string  $toolName  The name of the tool
     * @param  bool  $shouldQueue  Whether the tool should be queued
     */
    public function setToolQueueable(string $toolName, bool $shouldQueue = true): self
    {
        $this->queueableToolExecutionHandler->setToolQueueable($toolName, $shouldQueue);

        return $this;
    }

    /**
     * Register a callback for when a tool execution is queued.
     *
     * @param  callable  $callback  The callback function
     */
    public function onToolQueued(callable $callback): self
    {
        $this->queueableToolExecutionHandler->onQueued($callback);

        return $this;
    }

    /**
     * Register a tool function with queue configuration.
     *
     * @param  string  $name  The name of the function
     * @param  callable  $callback  The function to call
     * @param  array  $parameters  The function parameters schema
     * @param  string|null  $description  The function description
     * @param  bool|null  $shouldQueue  Whether the tool should be queued
     */
    public function withQueueableTool(
        string $name,
        callable $callback,
        array $parameters,
        ?string $description = null,
        ?bool $shouldQueue = true
    ): self {
        $this->withTool($name, $callback, $parameters, $description);
        $this->queueableToolExecutionHandler->setToolQueueable($name, $shouldQueue ?? true);

        return $this;
    }
}
