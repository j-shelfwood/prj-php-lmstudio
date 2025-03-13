<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Tools;

class ToolExecutionHandler
{
    /**
     * @var callable|null Callback for when a tool call is received
     */
    protected $onReceived = null;

    /**
     * @var callable|null Callback for when a tool is about to be executed
     */
    protected $onExecuting = null;

    /**
     * @var callable|null Callback for when a tool has been executed successfully
     */
    protected $onExecuted = null;

    /**
     * @var callable|null Callback for when a tool execution fails
     */
    protected $onError = null;

    /**
     * Set callback for when a tool call is received.
     *
     * @param  callable  $callback  Function to call when a tool call is received
     */
    public function onReceived(callable $callback): self
    {
        $this->onReceived = $callback;

        return $this;
    }

    /**
     * Set callback for when a tool is about to be executed.
     *
     * @param  callable  $callback  Function to call when a tool is about to be executed
     */
    public function onExecuting(callable $callback): self
    {
        $this->onExecuting = $callback;

        return $this;
    }

    /**
     * Set callback for when a tool has been executed successfully.
     *
     * @param  callable  $callback  Function to call when a tool has been executed successfully
     */
    public function onExecuted(callable $callback): self
    {
        $this->onExecuted = $callback;

        return $this;
    }

    /**
     * Set callback for when a tool execution fails.
     *
     * @param  callable  $callback  Function to call when a tool execution fails
     */
    public function onError(callable $callback): self
    {
        $this->onError = $callback;

        return $this;
    }

    /**
     * Handle a tool call being received.
     *
     * @param  array  $toolCall  The tool call data
     */
    public function handleReceived(array $toolCall): void
    {
        if ($this->onReceived) {
            call_user_func($this->onReceived, $toolCall);
        }
    }

    /**
     * Handle a tool about to be executed.
     *
     * @param  array  $toolCall  The tool call data
     * @param  callable  $executor  The function that will execute the tool
     */
    public function handleExecuting(array $toolCall, callable $executor): void
    {
        if ($this->onExecuting) {
            call_user_func($this->onExecuting, $toolCall, $executor);
        }
    }

    /**
     * Handle a tool that has been executed successfully.
     *
     * @param  array  $toolCall  The tool call data
     * @param  mixed  $result  The result of the tool execution
     */
    public function handleExecuted(array $toolCall, $result): void
    {
        if ($this->onExecuted) {
            call_user_func($this->onExecuted, $toolCall, $result);
        }
    }

    /**
     * Handle a tool execution that has failed.
     *
     * @param  array  $toolCall  The tool call data
     * @param  \Throwable  $error  The error that occurred
     */
    public function handleError(array $toolCall, \Throwable $error): void
    {
        if ($this->onError) {
            call_user_func($this->onError, $toolCall, $error);
        }
    }
}
