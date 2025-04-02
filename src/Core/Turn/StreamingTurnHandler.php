<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Turn;

use JsonException;
use Psr\Log\LoggerInterface;
use Shelfwood\LMStudio\Api\Model\ChatCompletionChunk;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Contract\TurnHandlerInterface;
use Shelfwood\LMStudio\Core\Conversation\ConversationState;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Exception\TurnTimeoutException;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Throwable;

class StreamingTurnHandler implements TurnHandlerInterface
{
    private const DEFAULT_STREAM_TIMEOUT = 60; // Seconds

    public function __construct(
        private readonly ChatService $chatService,
        private readonly ToolRegistry $toolRegistry,
        private readonly ToolExecutor $toolExecutor,
        private readonly EventHandler $eventHandler,
        private readonly StreamingHandler $streamingHandler,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * {@inheritdoc}
     */
    public function handle(ConversationState $state, ?int $timeout = null): string
    {
        $effectiveTimeout = $timeout ?? ($state->getOptions()['stream_timeout'] ?? self::DEFAULT_STREAM_TIMEOUT);
        $this->streamingHandler->reset();

        $turnComplete = false;
        $turnError = null;
        /** @var list<ToolCall>|null */
        $receivedToolCalls = null;
        $accumulatedContent = '';
        $finalContent = '';

        // Setup internal listeners on the injected StreamingHandler
        $streamEndListener = function ($tools) use (&$receivedToolCalls, &$turnComplete): void {
            $receivedToolCalls = $tools;
            $turnComplete = true;
        };
        $streamErrorListener = function ($error) use (&$turnError, &$turnComplete): void {
            $turnError = $error;
            $turnComplete = true;
        };
        $streamContentListener = function ($content) use (&$accumulatedContent): void {
            $accumulatedContent .= $content;
        };

        $this->streamingHandler->on('stream_end', $streamEndListener);
        $this->streamingHandler->on('stream_error', $streamErrorListener);
        $this->streamingHandler->on('stream_content', $streamContentListener);

        // Define the callback for the ChatService
        $chunkProcessorCallback = function (ChatCompletionChunk $chunk) use ($effectiveTimeout, &$startTime): void {
            // Check for timeout within the callback itself
            if ((time() - $startTime) > $effectiveTimeout) {
                throw new TurnTimeoutException(
                    sprintf('Streaming turn timed out after %d seconds while processing chunks.', $effectiveTimeout)
                );
            }
            $this->streamingHandler->handleChunk($chunk);
        };

        try {
            $tools = $this->toolRegistry->hasTools() ? $this->toolRegistry->getTools() : null;
            $options = $state->getOptions(); // Get base options
            $responseFormat = $options['response_format'] ?? null; // Extract potential ResponseFormat object
            unset($options['response_format'], $options['stream_timeout']); // Remove non-API options

            // --- Initiate Stream ---
            $startTime = time(); // Record start time before the call
            $this->chatService->createCompletionStream(
                $state->getModel(),
                $state->getMessages(),
                $chunkProcessorCallback, // Pass the processing callback
                $tools,
                $responseFormat, // Pass null or ResponseFormat object
                array_merge($options, ['stream' => true]) // Pass remaining options, ensuring stream=true
            );

            // --- Wait for stream completion (via event listeners) ---
            $waitStartTime = time();

            while (! $turnComplete) {
                usleep(10000); // Sleep for 10ms to avoid busy-waiting

                if ((time() - $waitStartTime) > $effectiveTimeout) {
                    // Also check timeout while waiting for the stream_end/error event
                    throw new TurnTimeoutException(
                        sprintf('Streaming turn timed out after %d seconds while waiting for stream end event.', $effectiveTimeout)
                    );
                }
            }

            // Check if an error occurred during streaming (caught by the listener)
            if ($turnError instanceof Throwable) {
                throw $turnError;
            }

            // --- Validate & Execute Tools (if received) ---
            if (! empty($receivedToolCalls)) {
                // Add initial assistant message (content accumulated + tool calls) to state
                $state->addAssistantMessage($accumulatedContent, $receivedToolCalls);

                $toolResults = [];
                $validToolCalls = [];

                foreach ($receivedToolCalls as $toolCall) {
                    $toolName = $toolCall->function->name ?? '';
                    $arguments = $toolCall->function->arguments ?? '';
                    $toolCallId = $toolCall->id;

                    // 1. Validate Name
                    if (empty($toolName)) {
                        $errorMessage = 'Received tool call with empty name during stream.';
                        $errorPayload = [
                            'error' => 'MalformedToolCall',
                            'message' => $errorMessage,
                            'details' => 'Tool name was empty.',
                            'received_arguments' => $arguments,
                            'tool_call_id' => $toolCallId,
                        ];
                        $this->logger->warning($errorMessage, ['tool_call' => $toolCall->toArray()]);
                        $toolResults[$toolCallId] = json_encode($errorPayload);

                        continue;
                    }

                    // 2. Validate Arguments (must be valid JSON)
                    try {
                        json_decode($arguments, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $e) {
                        $errorMessage = "Received tool call '{$toolName}' with invalid JSON arguments during stream.";
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

                        continue;
                    }

                    // If valid, add for execution
                    $validToolCalls[] = $toolCall;
                }

                // Execute only valid tool calls
                if (! empty($validToolCalls)) {
                    $executionResults = $this->toolExecutor->executeMany($validToolCalls);
                    $toolResults = array_merge($toolResults, $executionResults); // Merge results
                }

                // Add all tool result messages (validation errors + execution results)
                foreach ($toolResults as $id => $result) {
                    $state->addToolMessage((string) $id, $result); // Result is already string (JSON)
                }

                // --- Second Call (Non-Streaming) ---
                $finalResponse = $this->chatService->createCompletion(
                    $state->getModel(),
                    $state->getMessages(), // History now includes tool results
                    null, // No tools needed
                    $responseFormat, // Use original format if set
                    $options // Use original filtered options
                );
                $this->eventHandler->trigger('response', $finalResponse);

                $finalAssistantMessage = $finalResponse->choices[0]->message ?? null;

                if ($finalAssistantMessage === null) {
                    $this->logger->warning('LM Studio API returned no message choice on second call after stream.');
                    $finalContent = $accumulatedContent; // Fallback to accumulated content
                } else {
                    $finalContent = $finalAssistantMessage->content ?? '';
                    // Add final assistant message to state
                    $state->addMessage($finalAssistantMessage);
                }

            } else {
                // No tools called, accumulated content is final
                $finalContent = $accumulatedContent;

                // Add the complete assistant message (only content) to state
                if (! empty($finalContent)) {
                    $state->addAssistantMessage($finalContent);
                }
            }

            return $finalContent;

        } catch (Throwable $e) {
            // Ensure error event is triggered even for timeouts happening in the callback
            if ($e instanceof TurnTimeoutException && ! $turnError) {
                $turnError = $e; // Ensure the error state is set
                $turnComplete = true; // Ensure the wait loop terminates
            }
            $this->eventHandler->trigger('error', $e);

            throw $e;
        }
    }
}
