<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel\Conversation;

use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Queue\Queue as QueueDispatcherContract;
use Shelfwood\LMStudio\Core\Builder\ConversationBuilder;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Manager\ConversationManager;
use Shelfwood\LMStudio\Laravel\Tools\QueueableToolExecutionHandler;
use Shelfwood\LMStudio\LMStudioFactory;

/**
 * Extends ConversationBuilder to provide queueable tool execution capabilities.
 */
class QueueableConversationBuilder extends ConversationBuilder
{
    private ?QueueDispatcherContract $queueDispatcher = null;

    private ?bool $queueToolsByDefault = null;

    private array $queueableToolsConfig = [];

    /**
     * Create a new queueable conversation builder.
     *
     * @param  LMStudioFactory  $factory  The factory to resolve dependencies.
     * @param  string  $model  The initial model ID.
     */
    public function __construct(
        LMStudioFactory $factory,
        string $model
    ) {
        parent::__construct($factory, $model);
        // No need to store factory locally if not used elsewhere

        if (function_exists('app')) {
            $this->queueDispatcher = app(QueueDispatcherContract::class);
            $config = app(ConfigContract::class);
            $this->queueToolsByDefault = $config->get('lmstudio.queue.tools_by_default', false);
        } else {
            throw new \LogicException('Laravel application context not available to resolve queue dependencies.');
        }
    }

    /**
     * Mark a specific tool to be queued (or not).
     *
     * @param  string  $toolName  The name of the tool.
     * @param  bool  $shouldQueue  Whether to queue this tool's execution.
     * @return $this
     */
    public function setToolQueueable(string $toolName, bool $shouldQueue = true): self
    {
        $this->queueableToolsConfig[$toolName] = $shouldQueue;

        // This config will be passed to the QueueableToolExecutionHandler during build.
        return $this;
    }

    /**
     * Register a callback for when a tool execution is queued.
     * This configures the EventHandler that the Queueable executor will use.
     *
     * @param  callable(string, array, string): void  $callback  (toolName, arguments, toolCallId)
     * @return $this
     */
    public function onToolQueued(callable $callback): self
    {
        // The QueueableToolExecutionHandler triggers 'lmstudio.tool.queued'.
        // We need to register this on the EventHandler that the executor will use.
        // This relies on the parent builder's event registration mechanism.
        // We add a specific public method to the base builder to handle this.
        // For now, let's assume a method like `on('tool.queued', $callback)` exists or will be added.
        // $this->on('tool.queued', $callback); // This would call parent::on()
        // TODO: Add `onToolQueued` public method to base ConversationBuilder targeting 'tool.queued' event
        return $this;
    }

    /**
     * Register a tool and optionally mark it as queueable.
     *
     * @param  string  $name  Tool name.
     * @param  callable  $callback  Tool execution callback.
     * @param  array  $parameters  Tool parameters schema.
     * @param  string|null  $description  Optional description.
     * @param  bool|null  $shouldQueue  Queue this tool? (Defaults to config `lmstudio.queue.tools_by_default`).
     * @return $this
     */
    public function withQueueableTool(
        string $name,
        callable $callback,
        array $parameters,
        ?string $description = null,
        ?bool $shouldQueue = null
    ): self {
        $this->withTool($name, $callback, $parameters, $description);
        $this->setToolQueueable($name, $shouldQueue ?? $this->queueToolsByDefault ?? true);

        return $this;
    }

    /**
     * Build the ConversationManager, ensuring a QueueableToolExecutionHandler is used.
     */
    public function build(): ConversationManager
    {
        if ($this->queueDispatcher === null) {
            throw new \LogicException('Queue dispatcher is not available.');
        }

        // *** Let parent build resolve registry and event handler initially ***
        // We will create our Queueable executor using the resolved components from the factory
        // which parent::build() would also use if we didn't override the executor.

        // Get the factory instance (passed to parent constructor)
        // Need access to it - modify parent or store locally.
        // Let's modify parent ConversationBuilder to have protected $factory

        // *** Assuming parent ConversationBuilder has `protected readonly LMStudioFactory $factory;` ***
        $finalToolRegistry = $this->toolRegistry ?? $this->factory->getToolRegistry();
        $finalEventHandler = $this->eventHandler ?? $this->factory->getEventHandler(); // Get resolved handler from factory

        // Create the Queueable executor using resolved components
        $queueableExecutor = new QueueableToolExecutionHandler(
            registry: $finalToolRegistry,
            eventHandler: $finalEventHandler,
            queueDispatcher: $this->queueDispatcher,
            queueByDefault: $this->queueToolsByDefault ?? false
        );

        // Apply specific tool queue configurations
        foreach ($this->queueableToolsConfig as $toolName => $shouldQueue) {
            $queueableExecutor->setToolQueueable($toolName, $shouldQueue);
        }

        // Inject this specific executor *before* calling parent build
        $this->withToolExecutor($queueableExecutor);

        // Parent build will now use our queueable executor
        return parent::build();
    }
}
