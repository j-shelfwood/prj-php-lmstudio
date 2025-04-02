<?php

declare(strict_types=1);

namespace Shelfwood\Lmstudio\Core\Tool;

use Exception;
use Psr\Log\LoggerInterface;
use Shelfwood\Lmstudio\Api\Model\Tool\ToolCall;
use Shelfwood\Lmstudio\Core\Event\EventHandler;
use Shelfwood\Lmstudio\Core\Tool\Exception\ToolExecutionException;

/**
 * Handles tool execution and related events.
 */
class ToolExecutor
{
    public function __construct(
        protected readonly ToolRegistry $registry,
        protected readonly EventHandler $eventHandler,
        protected readonly LoggerInterface $logger
    ) {}

    /**
     * Execute a tool call and return its result as a string (JSON for structured data/errors).
     *
     * @return string JSON encoded result or error string.
     */
    public function execute(ToolCall $toolCall): string
    {
        $toolName = $toolCall->name;
        $arguments = $toolCall->arguments;
        $toolCallId = $toolCall->id;

        $this->eventHandler->trigger('tool.executing', [
            'name' => $toolName,
            'arguments' => $arguments,
            'id' => $toolCallId,
        ]);

        if (! $this->registry->hasTool($toolName)) {
            $errorMessage = "Tool '{$toolName}' not found";
            $errorPayload = [
                'error' => 'ToolNotFound',
                'tool_name' => $toolName,
                'message' => $errorMessage,
            ];
            $this->logger->warning($errorMessage, ['tool_call' => $toolCall->toArray()]);
            $this->eventHandler->trigger('tool.error', [
                'name' => $toolName,
                'arguments' => $arguments,
                'id' => $toolCallId,
                'error' => new Exception($errorMessage),
                'payload' => $errorPayload,
            ]);

            return json_encode($errorPayload);
        }

        try {
            $result = $this->registry->executeTool($toolName, $arguments);

            $this->eventHandler->trigger('tool.executed', [
                'name' => $toolName,
                'arguments' => $arguments,
                'id' => $toolCallId,
                'result' => $result,
            ]);

            return is_string($result) ? $result : json_encode($result);

        } catch (ToolExecutionException $e) {
            $errorPayload = [
                'error' => basename(str_replace('\\\\', '/', get_class($e))),
                'tool_name' => $toolName,
                'message' => $e->getMessage(),
                'details' => $e->getDetails(),
            ];
            $this->logger->warning('Tool execution failed: '.$e->getMessage(), [
                'tool_name' => $toolName,
                'arguments' => $arguments,
                'exception' => (string) $e,
                'payload' => $errorPayload,
            ]);
            $this->eventHandler->trigger('tool.error', [
                'name' => $toolName,
                'arguments' => $arguments,
                'id' => $toolCallId,
                'error' => $e,
                'payload' => $errorPayload,
            ]);

            return json_encode($errorPayload);

        } catch (Exception $e) {
            $errorPayload = [
                'error' => 'UnexpectedToolError',
                'tool_name' => $toolName,
                'message' => 'An unexpected error occurred during tool execution.',
                'details' => $e->getMessage(),
            ];
            $this->logger->error('Unexpected tool execution error: '.$e->getMessage(), [
                'tool_name' => $toolName,
                'arguments' => $arguments,
                'exception' => (string) $e,
                'payload' => $errorPayload,
            ]);
            $this->eventHandler->trigger('tool.error', [
                'name' => $toolName,
                'arguments' => $arguments,
                'id' => $toolCallId,
                'error' => $e,
                'payload' => $errorPayload,
            ]);

            return json_encode($errorPayload);
        }
    }

    /**
     * Execute multiple tool calls and return their results.
     *
     * @param  ToolCall[]  $toolCalls
     * @return array<string, string> Map of tool call IDs to their JSON encoded results/errors
     */
    public function executeMany(array $toolCalls): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $results[$toolCall->id] = $this->execute($toolCall);
        }

        return $results;
    }
}
