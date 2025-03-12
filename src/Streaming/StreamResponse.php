<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Streaming;

use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Http\Factories\RequestFactoryInterface;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\Tools\ToolResponse;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\StreamChunk;
use Shelfwood\LMStudio\ValueObjects\ToolCall;

/**
 * Handles streaming responses from the LM Studio API.
 */
class StreamResponse implements StreamResponseInterface
{
    protected StreamState $state;

    protected string $content = '';

    protected array $toolCallsReceived = [];

    protected bool $isToolCallComplete = false;

    /**
     * Create a new stream response.
     */
    public function __construct(
        protected LMStudioClientInterface $client,
        protected RequestFactoryInterface $requestFactory,
        protected ChatHistory $chatHistory,
        protected ?string $model = null,
        protected array $tools = [],
        protected ?ToolRegistry $toolRegistry = null,
        protected string $toolUseMode = 'auto',
        protected float $temperature = 0.7,
        protected ?int $maxTokens = null,
        protected bool $debug = false,
        protected $contentCallback = null,
        protected $toolCallCallback = null,
        protected $toolResultCallback = null,
        protected $completeCallback = null,
        protected $errorCallback = null,
        protected $stateChangeCallback = null
    ) {
        $this->state = StreamState::STARTING;
    }

    /**
     * Process the stream with a callback for each chunk.
     */
    public function process(callable $callback): void
    {
        $this->setState(StreamState::STARTING);

        try {
            // Create request using the factory
            $request = $this->requestFactory->createChatCompletionRequest(
                $this->chatHistory,
                $this->model,
                $this->temperature,
                $this->maxTokens,
                $this->tools,
                $this->toolUseMode,
                true // streaming
            );

            $this->setState(StreamState::STREAMING);

            // Get the streaming response
            $stream = $this->client->streamChatCompletion($request);

            // Process each chunk
            foreach ($stream as $chunk) {
                $standardizedChunk = $this->standardizeStreamChunk($chunk);
                $this->processStreamChunk($standardizedChunk, $callback);

                // If we've reached a terminal state, break out
                if ($this->state->isTerminal()) {
                    break;
                }
            }

            // If we didn't reach a terminal state, send a completion chunk
            if (! $this->state->isTerminal()) {
                $this->setState(StreamState::COMPLETED);
                $completionChunk = StreamChunk::completion();
                $callback($completionChunk);
                $this->invokeCallback($this->completeCallback, $this->content, $this->toolCallsReceived);
            }
        } catch (\Exception $e) {
            $this->setState(StreamState::ERROR);
            $errorChunk = StreamChunk::error($e->getMessage());
            $callback($errorChunk);
            $this->invokeCallback($this->errorCallback, $errorChunk);
        }
    }

    /**
     * Process a stream chunk.
     */
    protected function processStreamChunk(StreamChunk $chunk, callable $callback): void
    {
        // Send the chunk to the callback
        $callback($chunk);

        // Process content
        if ($chunk->getContent() !== null) {
            $this->content .= $chunk->getContent();
            $this->invokeCallback($this->contentCallback, $chunk);
        }

        // Process tool calls
        if (! empty($chunk->getToolCalls())) {
            $this->setState(StreamState::PROCESSING_TOOL_CALLS);

            foreach ($chunk->getToolCalls() as $toolCall) {
                $this->toolCallsReceived[] = $toolCall;
                $this->invokeCallback($this->toolCallCallback, $toolCall);

                // Execute the tool if we have a registry
                if ($this->toolRegistry !== null && $toolCall instanceof ToolCall) {
                    $this->executeToolCall($toolCall, $callback);
                }
            }
        }

        // Process completion
        if ($chunk->getFinishReason() !== null) {
            if ($chunk->getFinishReason()->value === 'tool_calls') {
                $this->isToolCallComplete = true;

                // If we have tool calls and they're complete, continue the conversation
                if (! empty($this->toolCallsReceived) && $this->isToolCallComplete) {
                    $this->setState(StreamState::CONTINUING);
                    $this->continueConversation($callback);
                }
            } elseif ($chunk->getFinishReason()->value === 'error') {
                $this->setState(StreamState::ERROR);
                $this->invokeCallback($this->errorCallback, $chunk);
            } else {
                $this->setState(StreamState::COMPLETED);
                $this->invokeCallback($this->completeCallback, $this->content, $this->toolCallsReceived);
            }
        }
    }

    /**
     * Execute a tool call.
     */
    protected function executeToolCall(ToolCall $toolCall, callable $callback): void
    {
        try {
            // Send tool start event
            $startChunk = new StreamChunk(
                state: StreamState::PROCESSING_TOOL_CALLS
            );
            $callback($startChunk);

            // Execute the tool
            $result = $this->toolRegistry->execute($toolCall);

            // Create a tool response
            $toolResponse = new ToolResponse(
                toolCallId: $toolCall->id,
                toolName: $toolCall->function->name,
                content: $result,
                status: 'success'
            );

            // Add the tool response to the chat history
            $this->chatHistory->addToolMessage(
                $result,
                $toolCall->id
            );

            // Fix: Create a StreamChunk with the tool response data instead of using toolResult
            $resultChunk = new StreamChunk(
                toolCalls: [$toolResponse],
                state: StreamState::PROCESSING_TOOL_CALLS
            );
            $callback($resultChunk);
            $this->invokeCallback($this->toolResultCallback, $toolResponse);
        } catch (\Exception $e) {
            // Create an error tool response
            $errorResponse = new ToolResponse(
                toolCallId: $toolCall->id,
                toolName: $toolCall->function->name,
                content: "Error: {$e->getMessage()}",
                status: 'error',
                error: $e->getMessage()
            );

            // Add the error response to the chat history
            $this->chatHistory->addToolMessage(
                "Error: {$e->getMessage()}",
                $toolCall->id
            );

            // Fix: Create a StreamChunk with the error response data
            $errorChunk = new StreamChunk(
                toolCalls: [$errorResponse],
                state: StreamState::PROCESSING_TOOL_CALLS,
                error: $e->getMessage()
            );
            $callback($errorChunk);
            $this->invokeCallback($this->toolResultCallback, $errorResponse);
        }
    }

    /**
     * Continue the conversation after tool calls.
     */
    protected function continueConversation(callable $callback): void
    {
        try {
            // Create a continuation request using the factory
            $request = $this->requestFactory->createChatCompletionRequest(
                $this->chatHistory,
                $this->model,
                $this->temperature,
                $this->maxTokens,
                $this->tools,
                $this->toolUseMode,
                true // streaming
            );

            // Get the streaming response
            $stream = $this->client->streamChatCompletion($request);

            // Process each chunk
            foreach ($stream as $chunk) {
                $standardizedChunk = $this->standardizeStreamChunk($chunk);

                // Send the chunk to the callback
                $callback($standardizedChunk);

                // Process content
                if ($standardizedChunk->getContent() !== null) {
                    $this->content .= $standardizedChunk->getContent();
                    $this->invokeCallback($this->contentCallback, $standardizedChunk);
                }

                // Process completion
                if ($standardizedChunk->getFinishReason() !== null) {
                    if ($standardizedChunk->getFinishReason()->value === 'error') {
                        $this->setState(StreamState::ERROR);
                        $this->invokeCallback($this->errorCallback, $standardizedChunk);
                    } else {
                        $this->setState(StreamState::COMPLETED);
                        $this->invokeCallback($this->completeCallback, $this->content, $this->toolCallsReceived);
                    }

                    break;
                }
            }
        } catch (\Exception $e) {
            $this->setState(StreamState::ERROR);
            $errorChunk = StreamChunk::error($e->getMessage());
            $callback($errorChunk);
            $this->invokeCallback($this->errorCallback, $errorChunk);
        }
    }

    /**
     * Standardize a stream chunk from the LM Studio API.
     */
    protected function standardizeStreamChunk(array $chunk): StreamChunk
    {
        $content = null;
        $toolCalls = [];
        $finishReason = null;
        $error = null;

        // Extract content if available
        if (isset($chunk['choices'][0]['delta']['content'])) {
            $content = $chunk['choices'][0]['delta']['content'];
        }

        // Extract tool calls if available
        if (isset($chunk['choices'][0]['delta']['tool_calls'])) {
            foreach ($chunk['choices'][0]['delta']['tool_calls'] as $toolCall) {
                $toolCalls[] = ToolCall::function(
                    $toolCall['function']['name'] ?? '',
                    $toolCall['function']['arguments'] ?? '{}',
                    $toolCall['id'] ?? null
                );
            }
        }

        // Extract finish reason if available
        if (isset($chunk['choices'][0]['finish_reason']) && $chunk['choices'][0]['finish_reason'] !== null) {
            $finishReason = $chunk['choices'][0]['finish_reason'];
        }

        // Extract error if available
        if (isset($chunk['error'])) {
            $error = $chunk['error']['message'] ?? 'Unknown error';
            $finishReason = 'error';
        }

        return new StreamChunk(
            $content,
            $toolCalls,
            $finishReason,
            $error
        );
    }

    /**
     * Set the state and invoke the state change callback.
     */
    protected function setState(StreamState $state): void
    {
        $oldState = $this->state;
        $this->state = $state;

        if ($oldState !== $state && $this->stateChangeCallback !== null) {
            call_user_func($this->stateChangeCallback, $state, $oldState);
        }
    }

    /**
     * Invoke a callback if it exists.
     */
    protected function invokeCallback(?callable $callback, ...$args): void
    {
        if ($callback !== null) {
            call_user_func($callback, ...$args);
        }
    }

    /**
     * Get the current state.
     */
    public function getState(): StreamState
    {
        return $this->state;
    }

    /**
     * Get the accumulated content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the received tool calls.
     */
    public function getToolCalls(): array
    {
        return $this->toolCallsReceived;
    }
}
