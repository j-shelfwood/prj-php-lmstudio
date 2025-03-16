<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Tools;

use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;

class ConversationToolExecutor
{
    private ToolRegistry $toolRegistry;

    private EventHandler $eventHandler;

    private ToolExecutionHandler $toolExecutionHandler;

    public function __construct(
        ToolRegistry $toolRegistry,
        EventHandler $eventHandler,
        ToolExecutionHandler $toolExecutionHandler
    ) {
        $this->toolRegistry = $toolRegistry;
        $this->eventHandler = $eventHandler;
        $this->toolExecutionHandler = $toolExecutionHandler;
    }

    public function executeToolCall(ToolCall $toolCall, ConversationInterfaceForExecutor $conversation): Message
    {
        $functionName = $toolCall->getName();
        $arguments = $toolCall->getArguments();
        $toolCallId = $toolCall->getId();

        // Trigger the legacy event handler
        $this->eventHandler->trigger('tool_call', $functionName, $arguments, $toolCallId);

        // Use the tool execution handler if available
        $this->toolExecutionHandler->handleReceived($toolCall->toArray());

        // Execute the tool if registered
        if ($this->toolRegistry->hasTool($functionName)) {
            try {
                // Notify that the tool is about to be executed
                $executor = function ($args) use ($functionName) {
                    return $this->toolRegistry->executeTool($functionName, $args);
                };
                $this->toolExecutionHandler->handleExecuting($toolCall->toArray(), $executor);

                // Execute the tool
                $result = $this->toolRegistry->executeTool($functionName, $arguments);
                $resultContent = is_string($result) ? $result : json_encode($result);

                // Notify that the tool has been executed successfully
                $this->toolExecutionHandler->handleExecuted($toolCall->toArray(), $result);

                // Create tool response message
                return Message::forToolResponse($resultContent, $toolCallId);
            } catch (\Exception $e) {
                // Trigger the legacy event handler
                $this->eventHandler->trigger('error', $e);
                // Use the tool execution handler for error handling if available
                $this->toolExecutionHandler->handleError($toolCall->toArray(), $e);

                // Create error tool response message
                return Message::forToolResponse("Error: {$e->getMessage()}", $toolCallId);
            }
        } else {
            $errorMessage = "Tool '{$functionName}' not found in registry.";
            $this->eventHandler->trigger('error', new \RuntimeException($errorMessage));
            $this->toolExecutionHandler->handleError($toolCall->toArray(), new \RuntimeException($errorMessage));

            return Message::forToolResponse($errorMessage, $toolCallId);
        }
    }
}
