<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;

class ExecuteToolJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public readonly int $tries;

    public readonly int $timeout;

    protected readonly string $toolName;

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

    public function handle(ToolRegistry $toolRegistry): void
    {
        if (! $toolRegistry->hasTool($this->toolName)) {
            $error = new RuntimeException("Tool '{$this->toolName}' not found in registry");
            Event::dispatch('lmstudio.tool.error', [$error, $this->toolCallId]);

            throw $error;
        }

        try {
            $result = $toolRegistry->executeTool($this->toolName, $this->parameters);
            Event::dispatch('lmstudio.tool.success', [$result, $this->toolCallId]);
        } catch (\Throwable $e) {
            Event::dispatch('lmstudio.tool.error', [$e, $this->toolCallId]);

            throw $e;
        }
    }
}
