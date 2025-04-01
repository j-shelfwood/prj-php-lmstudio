<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Turn;

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
        private readonly StreamingHandler $streamingHandler // Inject the specific handler instance for this turn
    ) {}

    /**
     * {@inheritdoc}
     */
    public function handle(ConversationState $state, ?int $timeout = null): string
    {
        $effectiveTimeout = $timeout ?? ($state->getOptions()['stream_timeout'] ?? self::DEFAULT_STREAM_TIMEOUT);

        $this->streamingHandler->reset(); // Reset before use

        $turnComplete = false;
        $turnError = null;
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

            // --- Process Tools (if received) ---
            if (! empty($receivedToolCalls)) {
                // Add initial assistant message (possibly empty content, with tool calls) to state
                $state->addAssistantMessage($accumulatedContent, $receivedToolCalls);

                $toolResults = $this->executeToolCalls($receivedToolCalls);

                // Add tool result messages to state
                foreach ($toolResults as $key => $result) {
                    $toolCallId = (string) $key;
                    $state->addToolMessage($toolCallId, is_string($result) ? $result : json_encode($result));
                }

                // --- Second Call (Non-Streaming) ---
                $finalResponse = $this->chatService->createCompletion(
                    $state->getModel(),
                    $state->getMessages(), // History now includes tool results
                    null, // No tools for second call
                    $responseFormat, // Pass original response format if set
                    $options // Pass original filtered options
                );

                $this->eventHandler->trigger('response', $finalResponse); // Trigger standard response event

                if (empty($finalResponse->choices)) {
                    $finalContent = $accumulatedContent; // Default to streamed content if second call is empty
                } else {
                    $finalChoice = $finalResponse->choices[0];
                    $finalContent = $finalChoice->message->content ?? '';

                    // Add final assistant response to state
                    if (! empty($finalContent)) {
                        $state->addAssistantMessage($finalContent);
                    }
                }
            } else {
                // No tools called, the accumulated content is the final response
                $finalContent = $accumulatedContent;

                // Add the complete assistant message to state
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
