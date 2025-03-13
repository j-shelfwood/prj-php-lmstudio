<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;

class ExecuteToolJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout;

    /**
     * Create a new job instance.
     *
     * @param  string  $toolName  The name of the tool to execute
     * @param  array  $arguments  The arguments to pass to the tool
     * @param  string  $toolCallId  The ID of the tool call
     * @param  callable|null  $onSuccess  Callback to execute on success
     * @param  callable|null  $onError  Callback to execute on error
     */
    public function __construct(
        public string $toolName,
        public array $arguments,
        public string $toolCallId,
        protected $onSuccess = null,
        protected $onError = null,
        ?int $tries = null,
        ?int $timeout = null,
        ?string $queue = null,
        ?string $connection = null
    ) {
        $this->tries = $tries ?? config('lmstudio.queue.tries', 3);
        $this->timeout = $timeout ?? config('lmstudio.queue.timeout', 60);

        if ($queue) {
            $this->onQueue($queue);
        } elseif ($queueName = config('lmstudio.queue.queue')) {
            $this->onQueue($queueName);
        }

        if ($connection) {
            $this->onConnection($connection);
        } elseif ($connectionName = config('lmstudio.queue.connection')) {
            $this->onConnection($connectionName);
        }
    }

    /**
     * Execute the job.
     */
    public function handle(ToolRegistry $toolRegistry): void
    {
        try {
            if (! $toolRegistry->hasTool($this->toolName)) {
                throw new \RuntimeException("Tool '{$this->toolName}' not found in registry");
            }

            $result = $toolRegistry->executeTool($this->toolName, $this->arguments);

            if ($this->onSuccess) {
                call_user_func($this->onSuccess, $result, $this->toolCallId);
            }
        } catch (\Throwable $e) {
            if ($this->onError) {
                call_user_func($this->onError, $e, $this->toolCallId);
            } else {
                throw $e;
            }
        }
    }
}
