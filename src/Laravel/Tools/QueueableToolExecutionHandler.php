<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel\Tools;

use Shelfwood\LMStudio\Core\Tools\ToolExecutionHandler;
use Shelfwood\LMStudio\Laravel\Jobs\ExecuteToolJob;

class QueueableToolExecutionHandler extends ToolExecutionHandler
{
    /**
     * @var bool Whether to queue tool executions by default
     */
    protected bool $queueByDefault;

    /**
     * @var array<string, bool> Map of tool names to whether they should be queued
     */
    protected array $queueableTools = [];

    /**
     * @var callable|null Callback to execute when a tool execution is queued
     */
    protected $onQueued = null;

    /**
     * @var string|null The queue connection to use
     */
    protected ?string $queueConnection = null;

    /**
     * Create a new queueable tool execution handler.
     *
     * @param  bool|null  $queueByDefault  Whether to queue tool executions by default
     */
    public function __construct(?bool $queueByDefault = null)
    {
        // Default to false if not specified and config function is not available
        $this->queueByDefault = $queueByDefault ?? false;
    }

    /**
     * Set whether a specific tool should be queued.
     *
     * @param  string  $toolName  The name of the tool
     * @param  bool  $shouldQueue  Whether the tool should be queued
     */
    public function setToolQueueable(string $toolName, bool $shouldQueue = true): self
    {
        $this->queueableTools[$toolName] = $shouldQueue;

        return $this;
    }

    /**
     * Check if a tool should be queued.
     *
     * @param  string  $toolName  The name of the tool
     * @return bool Whether the tool should be queued
     */
    public function shouldQueueTool(string $toolName): bool
    {
        return $this->queueableTools[$toolName] ?? $this->queueByDefault;
    }

    /**
     * Set callback for when a tool execution is queued.
     *
     * @param  callable  $callback  Function to call when a tool execution is queued
     */
    public function onQueued(callable $callback): self
    {
        $this->onQueued = $callback;

        return $this;
    }

    /**
     * Set the queue connection to use for dispatching jobs.
     *
     * @param  string  $connection  The queue connection name
     */
    public function setQueueConnection(string $connection): self
    {
        $this->queueConnection = $connection;

        return $this;
    }

    /**
     * Handle a tool about to be executed.
     *
     * @param  array  $toolCall  The tool call data
     * @param  callable  $executor  The function that will execute the tool
     */
    public function handleExecuting(array $toolCall, callable $executor): void
    {
        $functionName = $toolCall['function']['name'] ?? '';
        $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?? [];
        $toolCallId = $toolCall['id'] ?? '';

        // Call the parent handler first
        parent::handleExecuting($toolCall, $executor);

        // Check if this tool should be queued
        if ($this->shouldQueueTool($functionName)) {
            // Create and dispatch the job
            $job = new ExecuteToolJob(
                toolName: $functionName,
                arguments: $arguments,
                toolCallId: $toolCallId,
                onSuccess: function ($result, $toolCallId) use ($toolCall): void {
                    if ($this->onExecuted) {
                        call_user_func($this->onExecuted, $toolCall, $result);
                    }
                },
                onError: function ($error, $toolCallId) use ($toolCall): void {
                    if ($this->onError) {
                        call_user_func($this->onError, $toolCall, $error);
                    }
                }
            );

            // Set the queue connection if specified
            if ($this->queueConnection !== null) {
                $job->onConnection($this->queueConnection);
            }

            dispatch($job);

            // Call the queued callback if set
            if ($this->onQueued) {
                call_user_func($this->onQueued, $toolCall, $job);
            }
        }
    }
}
