<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Conversation;

use Shelfwood\LMStudio\Api\Contract\ConversationInterface;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Exception\ApiException;
use Shelfwood\LMStudio\Api\Model\ChatCompletionChunk;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Throwable;

class Conversation implements ConversationInterface
{
    private readonly ChatService $chatService;

    private readonly string $model;

    private array $messages = [];

    private array $options;

    public readonly ToolRegistry $toolRegistry;

    public readonly EventHandler $eventHandler;

    public readonly bool $streaming;

    public readonly ?StreamingHandler $streamingHandler;

    public readonly ?ToolExecutor $toolExecutor;

    /**
     * @param  ChatService  $chatService  The chat service
     * @param  string  $model  The model to use
     * @param  array  $options  Additional options
     * @param  ToolRegistry  $toolRegistry  The tool registry
     * @param  EventHandler  $eventHandler  The event handler
     * @param  bool  $streaming  Whether to enable streaming
     * @param  StreamingHandler|null  $streamingHandler  The streaming handler
     * @param  ToolExecutor  $toolExecutor  The tool executor
     */
    public function __construct(
        ChatService $chatService,
        string $model,
        array $options,
        ToolRegistry $toolRegistry,
        EventHandler $eventHandler,
        bool $streaming,
        ?StreamingHandler $streamingHandler,
        ToolExecutor $toolExecutor
    ) {
        $this->chatService = $chatService;
        $this->model = $model;
        $this->options = $options;
        $this->toolRegistry = $toolRegistry;
        $this->eventHandler = $eventHandler;
        $this->streaming = $streaming;
        $this->streamingHandler = $streamingHandler;
        $this->toolExecutor = $toolExecutor;

        if ($this->streaming) {
            $this->options['stream'] = true;
        }
    }

    /**
     * Add a message to the conversation.
     */
    public function addMessage(Message $message): self
    {
        $this->messages[] = $message;

        return $this;
    }

    /**
     * Add a system message to the conversation.
     */
    public function addSystemMessage(string $content): self
    {
        return $this->addMessage(new Message(Role::SYSTEM, $content));
    }

    /**
     * Add a user message to the conversation.
     */
    public function addUserMessage(string $content): self
    {
        return $this->addMessage(new Message(Role::USER, $content));
    }

    /**
     * Add an assistant message to the conversation.
     */
    public function addAssistantMessage(string $content): self
    {
        return $this->addMessage(new Message(Role::ASSISTANT, $content));
    }

    /**
     * Get all messages in the conversation.
     *
     * @return Message[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the model being used by the conversation.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the conversation options.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get a standard (non-streaming) response from the model.
     * Handles the two-step process if tools are used.
     *
     * Note: For streaming conversations, consider using `handleStreamingTurn()`
     * for a simplified workflow that manages the stream and potential tool calls.
     *
     * @return string The model's response
     *
     * @throws ApiException If the request fails
     */
    public function getResponse(): string
    {
        if ($this->streaming) {
            throw new \RuntimeException('Cannot get a single response when streaming is enabled. Use initiateStreamingResponse() instead.');
        }

        try {
            $tools = null;

            if ($this->toolRegistry->hasTools()) {
                $tools = $this->toolRegistry->getTools();
            }

            // --- First Call ---
            $response = $this->chatService->createCompletion(
                $this->model,
                $this->messages,
                $tools,
                null,
                $this->options
            );

            if (empty($response->choices)) {
                return '';
            }

            $choice = $response->choices[0];
            $initialContent = $choice->message->content;
            $toolCalls = $choice->message->toolCalls;

            // Add the assistant message (may contain text and/or tool calls)
            if (! empty($initialContent) || ! empty($toolCalls)) {
                $this->addMessage(new Message(Role::ASSISTANT, $initialContent, $toolCalls));
            }

            // --- Handle Tools if Present ---
            if (! empty($toolCalls)) {
                if ($this->toolExecutor === null) {
                    throw new \RuntimeException('ToolExecutor is required to handle tool calls.');
                }

                // Execute tools and add results to history
                $toolResults = $this->executeToolCalls($toolCalls); // Use helper

                // --- Second Call (after adding tool results) ---
                $finalResponse = $this->chatService->createCompletion(
                    $this->model,
                    $this->messages, // History now includes tool results
                    null, // Tools usually not needed for the second call
                    null,
                    $this->options
                );

                if (empty($finalResponse->choices)) {
                    return '';
                }

                $finalChoice = $finalResponse->choices[0];
                $finalContent = $finalChoice->message->content;

                // Add final assistant response to history
                if (! empty($finalContent)) {
                    $this->addMessage(new Message(Role::ASSISTANT, $finalContent));
                }

                return $finalContent ?? '';
            } else {
                // No tools called, return the initial content
                return $initialContent ?? '';
            }
        } catch (Throwable $e) {
            $this->eventHandler->trigger('error', $e);

            throw $e;
        }
    }

    /**
     * Initiates a streaming response from the model.
     * This method starts the stream but does NOT wait for it or process tools.
     * Listen to events on the StreamingHandler (obtained via getStreamingHandler())
     * to process the stream and handle tool calls after the stream ends.
     *
     * For a simpler, managed workflow that handles the entire streaming turn including
     * tool execution and final response retrieval, use `handleStreamingTurn()`.
     *
     * @throws ApiException If the API request fails.
     * @throws \RuntimeException If streaming is not enabled or handler is missing.
     */
    public function initiateStreamingResponse(): void
    {
        if (! $this->streaming) {
            throw new \RuntimeException('Streaming is not enabled for this conversation.');
        }

        if ($this->streamingHandler === null) {
            throw new \RuntimeException('Streaming handler is required for streaming responses.');
        }

        try {
            $this->streamingHandler->reset(); // Reset handler state for a new stream

            $tools = null;

            if ($this->toolRegistry->hasTools()) {
                $tools = $this->toolRegistry->getTools(); // Get Tool objects
            }

            // Define the callback that passes parsed chunks to the StreamingHandler.
            // REMOVED try-catch: Let exceptions bubble up to StreamingHandler->handleChunk
            $chunkProcessorCallback = function (ChatCompletionChunk $chunk): void {
                // Ensure handler exists (belt-and-suspenders)
                if ($this->streamingHandler) {
                    // Directly call handleChunk. It has its own internal error handling.
                    $this->streamingHandler->handleChunk($chunk);
                }
            };

            // Prepare the API request data
            // Options should already include 'stream: true' if streaming was enabled in constructor
            $requestOptions = $this->options;

            // Call the service to start the stream
            $this->chatService->createCompletionStream(
                $this->model,
                $this->messages,
                $chunkProcessorCallback, // Pass the simplified callback
                $tools,
                null, // responseFormat (can be added if needed)
                $requestOptions
            );

            // The method returns immediately after initiating the stream.
            // All processing happens via the StreamingHandler events outside this method.

        } catch (Throwable $e) {
            // Trigger general conversation error and rethrow
            $this->eventHandler->trigger('error', $e);

            throw $e;
        }
    }

    /**
     * Handles a complete streaming turn, including initiating the stream,
     * waiting for its completion, processing potential tool calls,
     * and returning the final textual response.
     *
     * This method simplifies the common streaming workflow. User-defined listeners
     * attached to the StreamingHandler *before* calling this method will still fire.
     *
     * @param  int  $timeout  The maximum time in seconds to wait for the initial stream to complete.
     * @return string The final textual response from the assistant.
     *
     * @throws \RuntimeException If streaming is not enabled, handler is missing, or the turn times out.
     * @throws Throwable If an error occurs during streaming, tool execution, or the final API call.
     */
    public function handleStreamingTurn(int $timeout = 60): string
    {
        if (! $this->streaming) {
            throw new \RuntimeException('Streaming is not enabled for this conversation. Cannot handle streaming turn.');
        }

        if ($this->streamingHandler === null) {
            throw new \RuntimeException('Streaming handler is required for streaming responses. Cannot handle streaming turn.');
        }

        $turnComplete = false;
        $turnError = null;
        $receivedToolCalls = null;
        $finalContent = ''; // Initialize final content

        $handler = $this->streamingHandler;

        // --- Temporary internal listeners for this turn ---
        $internalStreamEndListener = function ($tools) use (&$turnComplete, &$receivedToolCalls): void {
            // Ensure $tools is an array, even if empty, before checking emptiness
            $receivedToolCalls = (is_array($tools) && ! empty($tools)) ? $tools : null;
            $turnComplete = true;
        };
        $internalStreamErrorListener = function (Throwable $error) use (&$turnComplete, &$turnError): void {
            $turnError = $error;
            $turnComplete = true;
        };

        // Attach temporary listeners
        // Note: We assume EventHandler allows multiple listeners per event.
        // If it overwrites, this approach needs adjustment or reliance on handler reset.
        $handler->on('stream_end', $internalStreamEndListener);
        $handler->on('stream_error', $internalStreamErrorListener);

        try {
            // Initiate the streaming response
            $this->initiateStreamingResponse();

            // Wait loop for stream completion or timeout
            $startTime = microtime(true);

            while (! $turnComplete) {
                usleep(50000); // 50ms sleep to prevent busy-waiting

                if ((microtime(true) - $startTime) > $timeout) {
                    throw new \RuntimeException("Streaming turn timed out after {$timeout} seconds during initial stream.");
                }
            }

            // --- Cleanup temporary listeners (Best effort) ---
            // If EventHandler lacks removal, they persist but reset() in initiateStreamingResponse helps.
            // It's crucial that initiateStreamingResponse resets the handler state.

            // Check for stream errors captured by the internal listener
            if ($turnError !== null) {
                throw $turnError; // Rethrow the captured stream error
            }

            // --- Process Tool Calls (if received) ---
            if ($receivedToolCalls !== null) {
                // Tools were called by the model during the stream
                try {
                    // Execute tools and update history (adds tool messages)
                    $this->executeToolCalls($receivedToolCalls);

                    // --- Second API Call (Needed after tool results) ---
                    // Prepare options for the non-streaming call, explicitly removing/setting 'stream' to false
                    $finalCallOptions = $this->options;
                    $finalCallOptions['stream'] = false;

                    // Directly call createCompletion for the final response
                    $finalResponse = $this->chatService->createCompletion(
                        $this->model,
                        $this->messages, // History now includes tool results
                        null, // No tools needed for final answer
                        null,
                        $finalCallOptions // Use options with 'stream' => false
                    );

                    if (empty($finalResponse->choices)) {
                        $finalContent = '';
                    } else {
                        $finalChoice = $finalResponse->choices[0];
                        $finalContent = $finalChoice->message->content ?? '';

                        // Add final assistant response to history
                        if (! empty($finalContent)) {
                            $this->addMessage(new Message(Role::ASSISTANT, $finalContent));
                        }
                    }
                } catch (Throwable $e) {
                    // Handle errors during tool execution or the second API call
                    $this->eventHandler->trigger('error', $e); // Trigger general error

                    throw $e; // Rethrow the specific error
                }
            } else {
                // No tools were called. Retrieve content from the last assistant message.
                // The StreamingHandler should have added the assistant message with content.
                $lastMessage = ! empty($this->messages) ? end($this->messages) : null;

                if ($lastMessage && $lastMessage->role === Role::ASSISTANT && $lastMessage->toolCalls === null) {
                    $finalContent = $lastMessage->content ?? '';
                } else {
                    $finalContent = ''; // Default to empty or consider logging a warning
                }
            }
        } catch (Throwable $e) {
            // Trigger general conversation error if not already handled
            if ($turnError === null || $e !== $turnError) {
                $this->eventHandler->trigger('error', $e);
            }

            throw $e; // Rethrow exception caught during the process
        }

        return $finalContent;
    }

    /**
     * Executes a list of tool calls, adds the results to the conversation history,
     * and returns the results.
     * This should typically be called after a stream ends and provides tool calls,
     * or internally by getResponse().
     *
     * @param  ToolCall[]  $toolCalls  Array of ToolCall objects to execute.
     * @return array Map of tool call IDs to their results (or error messages).
     *
     * @throws \RuntimeException If ToolExecutor is unavailable.
     */
    public function executeToolCalls(array $toolCalls): array
    {
        if (empty($toolCalls)) {
            return [];
        }

        if ($this->toolExecutor === null) {
            throw new \RuntimeException('ToolExecutor is not available to execute tool calls.');
        }

        // Ensure the assistant message that requested the calls is in the history
        $lastMessage = ! empty($this->messages) ? end($this->messages) : null;

        // Check if last message is ASSISTANT and *contains these specific tool calls*
        // A simple check might just ensure the last message *could* have contained tool calls.
        // A more robust check would compare IDs if necessary.
        $needsAssistantMessage = true;

        if ($lastMessage && $lastMessage->role === Role::ASSISTANT) {
            // If the last message already has tool calls, assume it's the correct one.
            // This might need refinement if multiple assistant messages could precede tool execution.
            if (! empty($lastMessage->toolCalls)) {
                $needsAssistantMessage = false;
            }
        }

        if ($needsAssistantMessage) {
            // Add a minimal assistant message if history doesn't seem right
            $this->addMessage(new Message(Role::ASSISTANT, null, $toolCalls));
        }

        // Execute the tools via the executor
        $results = $this->toolExecutor->executeMany($toolCalls);

        // Add TOOL result messages to history
        foreach ($toolCalls as $toolCall) {
            $toolCallId = $toolCall->id;
            // Use array key exists for potentially null results
            $resultData = array_key_exists($toolCallId, $results) ? $results[$toolCallId] : ['error' => 'Execution result missing'];

            // Format the result content appropriately for the API
            if (is_array($resultData)) {
                // If the tool returned an array, JSON encode it.
                $resultContent = json_encode($resultData, JSON_UNESCAPED_SLASHES);
            } elseif (is_scalar($resultData) || is_null($resultData)) {
                // If scalar (string, int, float, bool) or null, cast to string.
                $resultContent = (string) $resultData;
            } else {
                // Fallback for objects or other types (might need adjustment)
                $resultContent = json_encode($resultData, JSON_UNESCAPED_SLASHES);
            }

            // Use the static factory method for clarity
            $this->addMessage(Message::forToolResponse($resultContent, $toolCallId));
        }

        return $results;
    }

    /**
     * Clear all messages from the conversation.
     */
    public function clearMessages(): self
    {
        $this->messages = [];

        return $this;
    }
}
