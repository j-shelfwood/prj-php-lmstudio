<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Conversation;

use Shelfwood\LMStudio\Api\Contract\ConversationInterface;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Exception\ApiException;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\Core\Tools\ConversationInterfaceForExecutor;
use Shelfwood\LMStudio\Core\Tools\ConversationToolExecutor;
use Shelfwood\LMStudio\Core\Tools\ToolExecutionHandler;

class Conversation implements ConversationInterface, ConversationInterfaceForExecutor
{
    private ChatService $chatService;

    private string $model;

    private array $messages = [];

    private array $options;

    private ToolRegistry $toolRegistry;

    private EventHandler $eventHandler;

    private bool $streaming;

    private ?StreamingHandler $streamingHandler;

    private ?ToolExecutionHandler $toolExecutionHandler;

    private ?ConversationToolExecutor $toolExecutor = null;

    /**
     * Track whether progress has been triggered.
     */
    private bool $progressTriggered = false;

    /**
     * @param  ChatService  $chatService  The chat service
     * @param  string  $model  The model to use
     * @param  array  $options  Additional options
     * @param  ToolRegistry|null  $toolRegistry  The tool registry
     * @param  EventHandler|null  $eventHandler  The event handler
     * @param  bool  $streaming  Whether to enable streaming
     * @param  StreamingHandler|null  $streamingHandler  The streaming handler
     * @param  ToolExecutionHandler|null  $toolExecutionHandler  The tool execution handler
     */
    public function __construct(
        ChatService $chatService,
        string $model,
        array $options = [],
        ?ToolRegistry $toolRegistry = null,
        ?EventHandler $eventHandler = null,
        bool $streaming = false,
        ?StreamingHandler $streamingHandler = null,
        ?ToolExecutionHandler $toolExecutionHandler = null
    ) {
        $this->chatService = $chatService;
        $this->model = $model;
        $this->options = $options;
        $this->toolRegistry = $toolRegistry ?? new ToolRegistry;
        $this->eventHandler = $eventHandler ?? new EventHandler;
        $this->streaming = $streaming;
        $this->streamingHandler = $streamingHandler;
        $this->toolExecutionHandler = $toolExecutionHandler;

        // Initialize the tool executor
        if ($this->toolRegistry !== null && $this->eventHandler !== null && $this->toolExecutionHandler !== null) {
            $this->toolExecutor = new ConversationToolExecutor(
                $this->toolRegistry,
                $this->eventHandler,
                $this->toolExecutionHandler
            );
        }

        // Set the stream option in the options array if streaming is enabled
        if ($this->streaming) {
            $this->options['stream'] = true;
        }
    }

    /**
     * Add a system message to the conversation.
     *
     * @param  string  $content  The message content
     */
    public function addSystemMessage(string $content): self
    {
        $this->messages[] = new Message(Role::SYSTEM, $content);

        return $this;
    }

    /**
     * Add a user message to the conversation.
     *
     * @param  string  $content  The message content
     */
    public function addUserMessage(string $content): self
    {
        $this->messages[] = new Message(Role::USER, $content);

        return $this;
    }

    /**
     * Add an assistant message to the conversation.
     *
     * @param  string  $content  The message content
     */
    public function addAssistantMessage(string $content): self
    {
        $this->messages[] = new Message(Role::ASSISTANT, $content);

        return $this;
    }

    /**
     * Add a tool message to the conversation.
     *
     * @param  string  $content  The message content
     * @param  string  $toolCallId  The tool call ID
     */
    public function addToolMessage(string $content, string $toolCallId): self
    {
        $this->messages[] = new Message(Role::TOOL, $content, null, $toolCallId);

        return $this;
    }

    /**
     * Get a response from the model.
     *
     * @return string The model's response
     *
     * @throws ApiException If the request fails
     */
    public function getResponse(): string
    {
        try {
            // Add tools to options if any are registered
            $options = $this->options;

            // Only include tools if there are actually tools registered
            if ($this->toolRegistry->hasTools()) {
                $toolsArray = $this->toolRegistry->getToolsArray();

                if (! empty($toolsArray)) {
                    $options['tools'] = $toolsArray;
                }
            }

            $completion = $this->chatService->createCompletion(
                $this->model,
                $this->messages,
                $options
            );

            $this->eventHandler->trigger('response', $completion);

            $choices = $completion->getChoices();

            if (empty($choices)) {
                return '';
            }

            $choice = $choices[0];
            $content = $choice->getContent();
            $toolCalls = $choice->hasToolCalls() ? $choice->getToolCalls() : null;

            // Add the assistant's response to the conversation
            if (! empty($content) || ! empty($toolCalls)) {
                // Convert tool calls array to ToolCall objects if they're not already
                $toolCallObjects = null;

                if (! empty($toolCalls)) {
                    $toolCallObjects = array_map(function ($toolCall) {
                        return $toolCall instanceof ToolCall ? $toolCall : ToolCall::fromArray($toolCall);
                    }, $toolCalls);
                }

                $this->messages[] = new Message(Role::ASSISTANT, $content ?? '', $toolCallObjects);
            }

            // Handle tool calls if present
            if (! empty($toolCalls)) {
                $this->handleToolCalls($toolCalls);
            }

            return $content ?? '';
        } catch (\Exception $e) {
            $this->eventHandler->trigger('error', $e);

            throw $e;
        }
    }

    /**
     * Get a streaming response from the model.
     *
     * @param  callable|null  $callback  The callback function to call for each chunk
     * @return string The complete response
     *
     * @throws ApiException If the request fails
     */
    public function getStreamingResponse(?callable $callback = null): string
    {
        if (! $this->streaming) {
            $this->options['stream'] = true;
            $this->streaming = true;
        }

        try {
            $fullContent = '';
            $toolCalls = null;
            $this->progressTriggered = false;

            // Add tools to options if any are registered
            $options = $this->options;

            // Only include tools if there are actually tools registered
            if ($this->toolRegistry->hasTools()) {
                $toolsArray = $this->toolRegistry->getToolsArray();

                if (! empty($toolsArray)) {
                    $options['tools'] = $toolsArray;
                }
            }

            $this->chatService->createCompletionStream(
                model: $this->model,
                messages: $this->messages,
                options: $options,
                callback: function ($chunk) use (&$fullContent, &$toolCalls, $callback): void {
                    // Trigger the legacy event handler
                    $this->eventHandler->trigger('chunk', $chunk);

                    // Call the legacy callback if provided
                    if ($callback !== null) {
                        $callback($chunk);
                    }

                    // Use the streaming handler if available
                    if ($this->streamingHandler !== null) {
                        $this->streamingHandler->handleChunk($chunk);
                    }

                    if (isset($chunk['choices'][0]['delta']['content'])) {
                        $fullContent .= $chunk['choices'][0]['delta']['content'];
                    }

                    if (isset($chunk['choices'][0]['delta']['tool_calls'])) {
                        if ($toolCalls === null) {
                            $toolCalls = [];
                        }

                        // Merge tool calls data
                        foreach ($chunk['choices'][0]['delta']['tool_calls'] as $index => $toolCallDelta) {
                            if (! isset($toolCalls[$index])) {
                                $toolCalls[$index] = [
                                    'id' => $toolCallDelta['id'] ?? '',
                                    'type' => $toolCallDelta['type'] ?? 'function',
                                    'function' => [
                                        'name' => $toolCallDelta['function']['name'] ?? '',
                                        'arguments' => $toolCallDelta['function']['arguments'] ?? '',
                                    ],
                                ];
                            } else {
                                if (isset($toolCallDelta['function']['name'])) {
                                    $toolCalls[$index]['function']['name'] .= $toolCallDelta['function']['name'];
                                }

                                if (isset($toolCallDelta['function']['arguments'])) {
                                    $toolCalls[$index]['function']['arguments'] .= $toolCallDelta['function']['arguments'];
                                }
                            }
                        }
                    }
                }
            );

            // Add the assistant's response to the conversation
            if (! empty($fullContent) || ! empty($toolCalls)) {
                // Convert tool calls array to ToolCall objects if present
                $toolCallObjects = null;

                if (! empty($toolCalls)) {
                    $toolCallObjects = array_map(function ($toolCall) {
                        return ToolCall::fromArray($toolCall);
                    }, $toolCalls);
                }

                $this->messages[] = new Message(Role::ASSISTANT, $fullContent, $toolCallObjects);
            }

            // Handle tool calls if present
            if (! empty($toolCalls)) {
                $this->handleToolCalls($toolCalls);
            }

            // Trigger progress event when streaming is complete, but only if no progress events have been triggered yet
            if (! $this->progressTriggered) {
                $this->eventHandler->trigger('progress', 1.0);
                $this->progressTriggered = true;
            }

            return $fullContent;
        } catch (\Exception $e) {
            $this->eventHandler->trigger('error', $e);

            // Use the streaming handler for error handling if available
            if ($this->streamingHandler !== null) {
                $this->streamingHandler->handleError($e);
            }

            throw $e;
        }
    }

    /**
     * Handle tool calls from the model.
     *
     * @param  array  $toolCalls  The tool calls
     */
    private function handleToolCalls(array $toolCalls): void
    {
        foreach ($toolCalls as $toolCall) {
            // If toolCall is already a ToolCall object, use it directly
            if ($toolCall instanceof ToolCall) {
                $toolCallObj = $toolCall;
            } else {
                // Create ToolCall object from array data
                $toolCallObj = ToolCall::fromArray($toolCall);
            }

            if ($this->toolExecutor !== null) {
                $toolResponseMessage = $this->toolExecutor->executeToolCall($toolCallObj, $this);
                $this->addMessage($toolResponseMessage);
            } else {
                // Fallback to old behavior if tool executor is not available
                $functionName = $toolCallObj->getName();
                $arguments = $toolCallObj->getArguments();
                $toolCallId = $toolCallObj->getId();

                // Trigger the legacy event handler
                $this->eventHandler->trigger('tool_call', $functionName, $arguments, $toolCallId);
            }
        }
    }

    /**
     * Get all messages in the conversation.
     *
     * @return array<Message> The messages
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Clear all messages in the conversation.
     */
    public function clearMessages(): self
    {
        $this->messages = [];

        return $this;
    }

    /**
     * Set the model to use.
     *
     * @param  string  $model  The model to use
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set additional options.
     *
     * @param  array  $options  Additional options
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get the model being used.
     *
     * @return string The model
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the additional options.
     *
     * @return array The options
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get the tool registry.
     *
     * @return ToolRegistry The tool registry
     */
    public function getToolRegistry(): ToolRegistry
    {
        return $this->toolRegistry;
    }

    /**
     * Get the event handler.
     *
     * @return EventHandler The event handler
     */
    public function getEventHandler(): EventHandler
    {
        return $this->eventHandler;
    }

    /**
     * Get the streaming handler.
     *
     * @return StreamingHandler|null The streaming handler
     */
    public function getStreamingHandler(): ?StreamingHandler
    {
        return $this->streamingHandler;
    }

    /**
     * Get the tool execution handler.
     *
     * @return ToolExecutionHandler|null The tool execution handler
     */
    public function getToolExecutionHandler(): ?ToolExecutionHandler
    {
        return $this->toolExecutionHandler;
    }

    /**
     * Check if streaming is enabled.
     *
     * @return bool Whether streaming is enabled
     */
    public function isStreaming(): bool
    {
        return $this->streaming;
    }

    /**
     * Enable or disable streaming.
     *
     * @param  bool  $streaming  Whether to enable streaming
     */
    public function setStreaming(bool $streaming): self
    {
        $this->streaming = $streaming;
        $this->options['stream'] = $streaming;

        return $this;
    }

    /**
     * Set the streaming handler.
     *
     * @param  StreamingHandler  $handler  The streaming handler
     */
    public function setStreamingHandler(StreamingHandler $handler): self
    {
        $this->streamingHandler = $handler;

        return $this;
    }

    /**
     * Set the tool execution handler.
     *
     * @param  ToolExecutionHandler  $handler  The tool execution handler
     */
    public function setToolExecutionHandler(ToolExecutionHandler $handler): self
    {
        $this->toolExecutionHandler = $handler;

        return $this;
    }

    /**
     * Mark progress as triggered.
     */
    public function markProgressTriggered(): void
    {
        $this->progressTriggered = true;
    }

    /**
     * Add a message to the conversation (implementing ConversationInterfaceForExecutor).
     *
     * @param  Message  $message  The message to add
     */
    public function addMessage(Message $message): void
    {
        $this->messages[] = $message;
    }
}
