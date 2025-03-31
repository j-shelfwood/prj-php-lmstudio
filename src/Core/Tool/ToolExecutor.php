<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Tool;

use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Core\Event\EventHandler;

/**
 * Handles tool execution and related events.
 */
class ToolExecutor
{
    public function __construct(
        protected readonly ToolRegistry $registry,
        protected readonly EventHandler $eventHandler
    ) {}

    /**
     * Execute a tool call and return its result.
     */
    public function execute(ToolCall $toolCall): mixed
    {
        $this->eventHandler->trigger('tool.executing', [
            'name' => $toolCall->name,
            'arguments' => $toolCall->arguments,
            'id' => $toolCall->id,
        ]);

        try {
            if (! $this->registry->hasTool($toolCall->name)) {
                throw new \RuntimeException("Tool '{$toolCall->name}' not found");
            }

            $result = $this->registry->executeTool($toolCall->name, $toolCall->arguments);

            $this->eventHandler->trigger('tool.executed', [
                'name' => $toolCall->name,
                'arguments' => $toolCall->arguments,
                'id' => $toolCall->id,
                'result' => $result,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->eventHandler->trigger('tool.error', [
                'name' => $toolCall->name,
                'arguments' => $toolCall->arguments,
                'id' => $toolCall->id,
                'error' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Execute multiple tool calls and return their results.
     *
     * @param  ToolCall[]  $toolCalls
     * @return array<string, mixed> Map of tool call IDs to their results
     */
    public function executeMany(array $toolCalls): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            try {
                $results[$toolCall->id] = $this->execute($toolCall);
            } catch (\Throwable $e) {
                $results[$toolCall->id] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }
}
