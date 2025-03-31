<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel\Tools;

use Illuminate\Contracts\Queue\Queue as QueueDispatcherContract;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\Laravel\Jobs\ExecuteToolJob;

class QueueableToolExecutionHandler extends ToolExecutor
{
    private ?string $queueConnection = null;

    /** @var array<string, bool> */
    private array $queueableTools = [];

    private readonly bool $queueByDefault;

    private readonly QueueDispatcherContract $queueDispatcher;

    public function __construct(
        ToolRegistry $registry,
        EventHandler $eventHandler,
        QueueDispatcherContract $queueDispatcher,
        ?bool $queueByDefault = false
    ) {
        parent::__construct($registry, $eventHandler);
        $this->queueDispatcher = $queueDispatcher;
        $this->queueByDefault = $queueByDefault ?? false;
    }

    public function setToolQueueable(string $toolName, bool $queueable): void
    {
        $this->queueableTools[$toolName] = $queueable;
    }

    public function shouldQueueTool(string $toolName): bool
    {
        return $this->queueableTools[$toolName] ?? $this->queueByDefault;
    }

    public function setQueueConnection(string $connection): void
    {
        $this->queueConnection = $connection;
    }

    public function getQueueConnection(): ?string
    {
        return $this->queueConnection;
    }

    public function onQueued(callable $callback): void
    {
        $this->eventHandler->on('lmstudio.tool.queued', $callback);
    }

    public function execute(ToolCall $toolCall): mixed
    {
        if ($this->shouldQueueTool($toolCall->name)) {
            $job = new ExecuteToolJob(
                toolName: $toolCall->name,
                parameters: $toolCall->arguments,
                toolCallId: $toolCall->id
            );

            if ($this->queueConnection) {
                $job->onConnection($this->queueConnection);
            }

            $this->queueDispatcher->dispatch($job);

            $this->eventHandler->trigger('lmstudio.tool.queued', [
                'name' => $toolCall->name,
                'arguments' => $toolCall->arguments,
                'id' => $toolCall->id,
            ]);

            return null;
        }

        return parent::execute($toolCall);
    }

    protected function handleToolSuccess($result, string $toolCallId): void
    {
        $this->eventHandler->trigger('tool.success', [$result, $toolCallId]);
    }

    protected function handleToolError(\Throwable $error, string $toolCallId): void
    {
        $this->eventHandler->trigger('tool.error', [$error, $toolCallId]);
    }
}
