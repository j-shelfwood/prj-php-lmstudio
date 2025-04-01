<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Turn;

use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
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
        private readonly EventHandler $eventHandler
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

            // Check for tool calls in the response
            $toolCalls = $response->getToolCalls();

            if (empty($response->choices)) {
                return '';
            }

            $choice = $response->choices[0];
            $initialContent = $choice->message->content;

            // Add the assistant's first message (content and/or tool calls) to state
            if (! empty($initialContent)) {
                $state->addAssistantMessage($initialContent);
            }

            // --- Handle Tools if Present ---
            if (! empty($toolCalls)) {
                $toolResults = $this->executeToolCalls($toolCalls);

                // Add tool result messages to state
                foreach ($toolResults as $key => $result) {
                    $toolCallId = (string) $key;
                    $state->addToolMessage($toolCallId, is_string($result) ? $result : json_encode($result));
                }

                // --- Second Call (after adding tool results) ---
                $finalResponse = $this->chatService->createCompletion(
                    $state->getModel(),
                    $state->getMessages(), // History now includes tool results
                    null, // Tools usually not needed for the second call
                    null,
                    $state->getOptions() // Pass initial options again
                );

                // Trigger response event for the second call
                $this->eventHandler->trigger('response', $finalResponse);

                if (empty($finalResponse->choices)) {
                    return ''; // Or throw? Consider behavior if second call yields nothing.
                }

                $finalChoice = $finalResponse->choices[0];
                $finalContent = $finalChoice->message->content;

                // Add final assistant response to state
                if (! empty($finalContent)) {
                    $state->addAssistantMessage($finalContent);
                }

                return $finalContent ?? '';
            } else {
                // No tools called, return the initial content
                return $initialContent ?? '';
            }
        } catch (Throwable $e) {
            $this->eventHandler->trigger('error', $e);

            throw $e; // Re-throw the original exception
        }
    }

    /**
     * Executes tool calls and returns an array of results keyed by tool call ID.
     *
     * @param  list<ToolCall>  $toolCalls
     * @return array<string, mixed> Map of [tool_call_id => result]
     *
     * @throws Throwable If tool execution fails.
     */
    private function executeToolCalls(array $toolCalls): array
    {
        return $this->toolExecutor->executeMany($toolCalls);
    }
}
