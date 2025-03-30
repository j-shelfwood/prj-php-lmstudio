<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel\Tools;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\Laravel\Jobs\ExecuteToolJob;

class QueueableToolExecutionHandler extends ToolExecutor
{
    private array $queueableTools = [];

    private ?string $queueConnection = null;

    private bool $queueByDefault;

    private ?EventHandler $eventHandler;

    private ?ToolRegistry $toolRegistry;

    public function __construct(?bool $queueByDefault = false, ?EventHandler $eventHandler = null)
    {
        $this->queueByDefault = $queueByDefault ?? false;
        $this->eventHandler = $eventHandler ?? new EventHandler;
        $this->toolRegistry = new ToolRegistry;

        parent::__construct($this->toolRegistry, $this->eventHandler);

        // Listen for tool execution events
        Event::listen('lmstudio.tool.success', function ($result, $toolCallId): void {
            $this->handleToolSuccess($result, $toolCallId);
        });

        Event::listen('lmstudio.tool.error', function ($error, $toolCallId): void {
            $this->handleToolError($error, $toolCallId);
        });
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
                $toolCall->name,
                $toolCall->arguments,
                $toolCall->id
            );

            if ($this->queueConnection) {
                $job->onConnection($this->queueConnection);
            }

            Queue::dispatch($job);

            Event::dispatch('lmstudio.tool.queued', [
                $toolCall->name,
                $toolCall->arguments,
                $toolCall->id,
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
