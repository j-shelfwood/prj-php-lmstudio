<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Builder;

use Shelfwood\LMStudio\Core\Conversation\ConversationState;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Manager\ConversationManager;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\Core\Turn\NonStreamingTurnHandler;
use Shelfwood\LMStudio\Core\Turn\StreamingTurnHandler;
use Shelfwood\LMStudio\LMStudioFactory;

/**
 * Builder for creating and configuring ConversationManager instances.
 */
class ConversationBuilder
{
    protected readonly LMStudioFactory $factory;

    private string $model;

    /** @var array<string, mixed> */
    private array $options = [];

    private ?ToolRegistry $toolRegistry = null;

    private ?EventHandler $eventHandler = null;

    private bool $streaming = false;

    private ?StreamingHandler $streamingHandler = null;

    private ?ToolExecutor $toolExecutor = null;

    private EventHandler $internalEventHandler;

    private ?StreamingHandler $internalStreamingHandler = null;

    /**
     * Create a new ConversationBuilder.
     *
     * @param  LMStudioFactory  $factory  The factory to resolve dependencies.
     * @param  string  $model  The initial model ID.
     */
    public function __construct(
        LMStudioFactory $factory,
        string $model
    ) {
        $this->factory = $factory;
        $this->model = $model;
        $this->internalEventHandler = new EventHandler;
    }

    public function withModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function withOptions(array $options): self
    {
        $this->options = array_merge($options, $this->options);

        return $this;
    }

    public function withTool(string $name, callable $callback, array $parameters, ?string $description = null): self
    {
        if ($this->toolRegistry === null) {
            $this->toolRegistry = $this->factory->getToolRegistry();
        }
        $this->toolRegistry->registerTool($name, $callback, $parameters, $description);

        return $this;
    }

    public function withStreaming(bool $streaming = true): self
    {
        $this->streaming = $streaming;

        if ($streaming && $this->internalStreamingHandler === null && $this->streamingHandler === null) {
            $this->internalStreamingHandler = $this->factory->createStreamingHandler();
        }

        if (! $streaming) {
            $this->streamingHandler = null;
            $this->internalStreamingHandler = null;
        }
        unset($this->options['stream']);

        return $this;
    }

    /**
     * Provide an external StreamingHandler instance.
     * Enabling streaming is implied.
     */
    public function withStreamingHandler(StreamingHandler $handler): self
    {
        $this->streamingHandler = $handler;
        $this->internalStreamingHandler = null;
        $this->streaming = true;
        unset($this->options['stream']);

        return $this;
    }

    /**
     * Provide an external ToolRegistry instance.
     * Note: Tools added via withTool() might target the factory's default registry
     * before this method is called. Call this early if providing your own.
     */
    public function withToolRegistry(ToolRegistry $registry): self
    {
        $this->toolRegistry = $registry;

        return $this;
    }

    /** Provide an external ToolExecutor instance. */
    public function withToolExecutor(ToolExecutor $executor): self
    {
        $this->toolExecutor = $executor;

        return $this;
    }

    /** Provide an external EventHandler instance for general events. */
    public function withEventHandler(EventHandler $handler): self
    {
        $this->eventHandler = $handler;

        return $this;
    }

    public function onResponse(callable $callback): self
    {
        $this->internalEventHandler->on('response', $callback);

        return $this;
    }

    public function onError(callable $callback): self
    {
        $this->internalEventHandler->on('error', $callback);

        return $this;
    }

    public function onToolExecuting(callable $callback): self
    {
        $this->internalEventHandler->on('tool.executing', $callback);

        return $this;
    }

    public function onToolExecuted(callable $callback): self
    {
        $this->internalEventHandler->on('tool.executed', $callback);

        return $this;
    }

    public function onToolError(callable $callback): self
    {
        $this->internalEventHandler->on('tool.error', $callback);

        return $this;
    }

    private function ensureInternalStreamingHandler(): StreamingHandler
    {
        if ($this->streamingHandler) {
            throw new \LogicException('Cannot configure stream events when an external StreamingHandler is provided via withStreamingHandler(). Attach listeners directly to your handler instance.');
        }

        if ($this->internalStreamingHandler === null) {
            $this->withStreaming(true);
        }

        return $this->internalStreamingHandler;
    }

    public function onStreamStart(callable $callback): self
    {
        $this->ensureInternalStreamingHandler()->on('stream_start', $callback);

        return $this;
    }

    public function onStreamContent(callable $callback): self
    {
        $this->ensureInternalStreamingHandler()->on('stream_content', $callback);

        return $this;
    }

    public function onStreamToolCallStart(callable $callback): self
    {
        $this->ensureInternalStreamingHandler()->on('stream_tool_call_start', $callback);

        return $this;
    }

    public function onStreamToolCallDelta(callable $callback): self
    {
        $this->ensureInternalStreamingHandler()->on('stream_tool_call_delta', $callback);

        return $this;
    }

    public function onStreamToolCallEnd(callable $callback): self
    {
        $this->ensureInternalStreamingHandler()->on('stream_tool_call_end', $callback);

        return $this;
    }

    public function onStreamEnd(callable $callback): self
    {
        $this->ensureInternalStreamingHandler()->on('stream_end', $callback);

        return $this;
    }

    public function onStreamError(callable $callback): self
    {
        $this->ensureInternalStreamingHandler()->on('stream_error', $callback);

        return $this;
    }

    /**
     * Build the ConversationManager instance.
     */
    public function build(): ConversationManager
    {
        $finalToolRegistry = $this->toolRegistry ?? $this->factory->getToolRegistry();
        $finalEventHandler = $this->eventHandler ?? $this->internalEventHandler;
        $finalStreamProcessor = $this->streamingHandler ?? $this->internalStreamingHandler;
        $finalToolExecutor = $this->toolExecutor ?? $this->factory->createToolExecutor($finalToolRegistry, $finalEventHandler);

        $apiOptions = $this->options;
        unset($apiOptions['stream_timeout']);
        $conversationState = new ConversationState($this->model, $apiOptions);

        // Instantiate handlers directly using dependencies from the factory
        $nonStreamingHandler = new NonStreamingTurnHandler(
            $this->factory->getChatService(),
            $finalToolRegistry,
            $finalToolExecutor,
            $finalEventHandler,
            $this->factory->getLogger() // Assuming a getLogger() method exists on the factory
        );

        $streamingTurnHandler = null;

        if ($this->streaming) {
            if ($finalStreamProcessor === null) {
                throw new \LogicException('Streaming is enabled but no StreamingHandler (stream processor) is available.');
            }
            // Instantiate handlers directly
            $streamingTurnHandler = new StreamingTurnHandler(
                $this->factory->getChatService(),
                $finalToolRegistry,
                $finalToolExecutor,
                $finalEventHandler,
                $finalStreamProcessor,
                $this->factory->getLogger() // Assuming a getLogger() method exists on the factory
            );
        }

        // Merge listeners from internal handler to the final handler if necessary
        if ($this->eventHandler === null && $this->internalEventHandler->hasAnyCallbacks()) {
            // If user didn't provide an external handler, but configured the internal one,
            // $finalEventHandler is $this->internalEventHandler, so listeners are already there.
            // No action needed here.
        }
        // Similar logic for stream listeners: they are configured on the instance ($finalStreamProcessor) directly.

        // Create the manager
        $manager = new ConversationManager(
            state: $conversationState,
            nonStreamingHandler: $nonStreamingHandler,
            streamingTurnHandler: $streamingTurnHandler,
            eventHandler: $finalEventHandler,
            streamProcessor: $finalStreamProcessor,
            isStreaming: $this->streaming
        );

        return $manager;
    }
}
