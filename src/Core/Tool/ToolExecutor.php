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
        private ToolRegistry $registry,
        private EventHandler $eventHandler
    ) {}

    /**
     * Execute a tool call and return its result.
     */
    public function execute(ToolCall $toolCall): mixed
    {
        $name = $toolCall->getName();
        $arguments = $toolCall->getArguments();

        $this->eventHandler->trigger('tool.executing', [
            'name' => $name,
            'arguments' => $arguments,
            'id' => $toolCall->getId(),
        ]);

        try {
            if (! $this->registry->hasTool($name)) {
                throw new \RuntimeException("Tool '{$name}' not found");
            }

            $result = $this->registry->executeTool($name, $arguments);

            $this->eventHandler->trigger('tool.executed', [
                'name' => $name,
                'arguments' => $arguments,
                'id' => $toolCall->getId(),
                'result' => $result,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->eventHandler->trigger('tool.error', [
                'name' => $name,
                'arguments' => $arguments,
                'id' => $toolCall->getId(),
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
                $results[$toolCall->getId()] = $this->execute($toolCall);
            } catch (\Throwable $e) {
                $results[$toolCall->getId()] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }
}
