<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Shelfwood\LMStudio\LMStudioFactory;
use Throwable;

class ExecuteToolJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public readonly int $tries;

    public readonly int $timeout;

    protected readonly string $toolName;

    /** @var array<string, mixed> */
    protected readonly array $parameters;

    protected readonly string $toolCallId;

    public function __construct(
        string $toolName,
        array $parameters,
        string $toolCallId,
        ?int $tries = null,
        ?int $timeout = null
    ) {
        $this->timeout = $timeout ?? config('lmstudio.queue.timeout', 60);
        $this->tries = $tries ?? config('lmstudio.queue.tries', 3);

        if ($queue = config('lmstudio.queue.queue')) {
            $this->onQueue($queue);
        }

        if ($connection = config('lmstudio.queue.connection')) {
            $this->onConnection($connection);
        }

        $this->toolName = $toolName;
        $this->parameters = $parameters;
        $this->toolCallId = $toolCallId;
    }

    /**
     * Execute the job.
     *
     * @param  LMStudioFactory  $factory  Resolved from container.
     *
     * @throws Throwable If tool execution fails.
     * @throws RuntimeException If tool is not found.
     */
    public function handle(LMStudioFactory $factory): void
    {
        $toolRegistry = $factory->getToolRegistry();
        $eventHandler = $factory->getEventHandler();

        if (! $toolRegistry->hasTool($this->toolName)) {
            $error = new RuntimeException("Tool '{$this->toolName}' not found in registry");
            $eventHandler->trigger('tool.error', $this->toolName, $this->parameters, $this->toolCallId, $error);

            throw $error;
        }

        try {
            $result = $toolRegistry->executeTool($this->toolName, $this->parameters, $this->toolCallId);
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
