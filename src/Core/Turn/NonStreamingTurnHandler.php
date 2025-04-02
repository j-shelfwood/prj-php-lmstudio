<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Turn;

use JsonException;
use Psr\Log\LoggerInterface;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Contract\TurnHandlerInterface;
use Shelfwood\LMStudio\Core\Conversation\ConversationState;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Throwable;

class NonStreamingTurnHandler implements TurnHandlerInterface
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly ToolRegistry $toolRegistry,
        private readonly ToolExecutor $toolExecutor,
        private readonly EventHandler $eventHandler,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * {@inheritdoc}
     */
    public function handle(ConversationState $state, ?int $timeout = null): string
    {
        // Note: $timeout parameter is not directly used in non-streaming HTTP calls,
        // but kept for interface consistency. Actual timeouts are handled by HttpClient config.

        try {
            $tools = $this->toolRegistry->hasTools() ? $this->toolRegistry->getTools() : null;
            $options = $state->getOptions();
            $responseFormat = $options['response_format'] ?? null;
            unset($options['response_format'], $options['stream_timeout']); // Ensure non-API options removed

            // Initial call
            $response = $this->chatService->createCompletion(
                $state->getModel(),
                $state->getMessages(),
                $tools, // Should be null if hasTools is false
                $responseFormat,
                $options
            );

            $this->eventHandler->trigger('response', $response);

            $assistantMessage = $response->choices[0]->message ?? null;

            if ($assistantMessage === null) {
                $this->logger->warning('LM Studio API returned no message choice.');

                return '';
            }

            // Add initial assistant message (might have content and/or tool calls)
            $state->addMessage($assistantMessage);

            // Get Tool Calls
            $toolCalls = $assistantMessage->tool_calls ?? [];

            // --- Validate Tool Calls & Execute ---
            $toolResults = [];
            $validToolCalls = [];

            if (! empty($toolCalls)) {
                foreach ($toolCalls as $toolCall) {
                    $toolName = $toolCall->function->name ?? '';
                    $arguments = $toolCall->function->arguments ?? '';
                    $toolCallId = $toolCall->id;

                    // 1. Validate Name
                    if (empty($toolName)) {
                        $errorMessage = 'Received tool call with empty name.';
                        $errorPayload = [
                            'error' => 'MalformedToolCall',
                            'message' => $errorMessage,
                            'details' => 'Tool name was empty.',
                            'received_arguments' => $arguments,
                            'tool_call_id' => $toolCallId,
                        ];
                        $this->logger->warning($errorMessage, ['tool_call' => $toolCall->toArray()]);
                        $toolResults[$toolCallId] = json_encode($errorPayload);

                        continue; // Skip to next tool call
                    }

                    // 2. Validate Arguments (must be valid JSON)
                    try {
                        json_decode($arguments, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $e) {
                        $errorMessage = "Received tool call '{$toolName}' with invalid JSON arguments.";
                        $errorPayload = [
                            'error' => 'MalformedToolCall',
                            'tool_name' => $toolName,
                            'message' => $errorMessage,
                            'details' => 'Arguments could not be parsed as JSON: '.$e->getMessage(),
                            'received_arguments' => $arguments,
                            'tool_call_id' => $toolCallId,
                        ];
                        $this->logger->warning($errorMessage, ['tool_call' => $toolCall->toArray(), 'json_error' => $e->getMessage()]);
                        $toolResults[$toolCallId] = json_encode($errorPayload);

                        continue; // Skip to next tool call
                    }

                    // If validation passed, add to list for execution
                    $validToolCalls[] = $toolCall;
                }

                // Execute only the valid tool calls
                if (! empty($validToolCalls)) {
                    $executionResults = $this->toolExecutor->executeMany($validToolCalls);
                    // Merge execution results with validation errors
                    $toolResults = array_merge($toolResults, $executionResults);
                }

                // Add tool result messages (validation errors + execution results) to state
                foreach ($toolResults as $id => $result) {
                    $state->addToolMessage((string) $id, $result); // Result is already a string (JSON)
                }

                // --- Second Call (after adding tool results) ---
                $finalResponse = $this->chatService->createCompletion(
                    $state->getModel(),
                    $state->getMessages(),
                    null, // No tools needed
                    null,
                    $state->getOptions()
                );
                $this->eventHandler->trigger('response', $finalResponse);

                $finalAssistantMessage = $finalResponse->choices[0]->message ?? null;

                if ($finalAssistantMessage === null) {
                    $this->logger->warning('LM Studio API returned no message choice on second call.');

                    return ''; // Or maybe return initial content?
                }

                // Add final assistant response to state
                $state->addMessage($finalAssistantMessage);

                return $finalAssistantMessage->content ?? '';
            } else {
                // No tools called, return the initial content
                return $assistantMessage->content ?? '';
            }
        } catch (Throwable $e) {
            $this->eventHandler->trigger('error', $e);

            throw $e;
        }
    }
}
