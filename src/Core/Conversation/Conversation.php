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
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;

class Conversation implements ConversationInterface
{
    private ChatService $chatService;

    private string $model;

    private array $messages = [];

    private array $options;

    private ToolRegistry $toolRegistry;

    private EventHandler $eventHandler;

    private bool $streaming;

    private ?StreamingHandler $streamingHandler;

    private ?ToolExecutor $toolExecutor;

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
     * @param  ToolExecutor|null  $toolExecutor  The tool executor
     */
    public function __construct(
        ChatService $chatService,
        string $model,
        array $options = [],
        ?ToolRegistry $toolRegistry = null,
        ?EventHandler $eventHandler = null,
        bool $streaming = false,
        ?StreamingHandler $streamingHandler = null,
        ?ToolExecutor $toolExecutor = null
    ) {
        $this->chatService = $chatService;
        $this->model = $model;
        $this->options = $options;
        $this->toolRegistry = $toolRegistry ?? new ToolRegistry;
        $this->eventHandler = $eventHandler ?? new EventHandler;
        $this->streaming = $streaming;
        $this->streamingHandler = $streamingHandler;
        $this->toolExecutor = $toolExecutor ?? new ToolExecutor($this->toolRegistry, $this->eventHandler);

        // Set the stream option in the options array if streaming is enabled
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
     * Get all messages in the conversation.
     *
     * @return Message[]
     */
    public function getMessages(): array
    {
        return $this->messages;
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
            $options = $this->options;

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
                $toolCallObjects = null;

                if (! empty($toolCalls)) {
                    $toolCallObjects = array_map(function ($toolCall) {
                        return $toolCall instanceof ToolCall ? $toolCall : ToolCall::fromArray($toolCall);
                    }, $toolCalls);

                    // Execute tool calls and add their responses
                    $results = $this->toolExecutor->executeMany($toolCallObjects);

                    foreach ($results as $toolCallId => $result) {
                        $resultContent = is_string($result) ? $result : json_encode($result);
                        $this->messages[] = new Message(Role::TOOL, $resultContent, null, $toolCallId);
                    }
                }

                $this->messages[] = new Message(Role::ASSISTANT, $content ?? '', $toolCallObjects);
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

        if ($this->streamingHandler === null) {
            throw new \RuntimeException('Streaming handler is required for streaming responses');
        }

        try {
            $fullContent = '';
            $toolCalls = [];

            // Set up streaming handler callbacks
            $this->streamingHandler
                ->on('stream_content', function ($content) use (&$fullContent, $callback): void {
                    $fullContent .= $content;

                    if ($callback !== null) {
                        $callback(['choices' => [['delta' => ['content' => $content]]]]);
                    }
                })
                ->on('stream_tool_call', function ($data) use (&$toolCalls): void {
                    $index = $data['index'];
                    $toolCalls[$index] = $data['tool_call'];
                });

            // Add tools to options if any are registered
            if ($this->toolRegistry->hasTools()) {
                $toolsArray = $this->toolRegistry->getToolsArray();

                if (! empty($toolsArray)) {
                    $this->options['tools'] = $toolsArray;
                }
            }

            // Create completion stream
            $this->chatService->createCompletionStream(
                $this->model,
                $this->messages,
                function ($chunk): void {
                    $this->streamingHandler->handleChunk($chunk);
                },
                $this->options
            );

            // Add the assistant's response to the conversation
            if (! empty($fullContent) || ! empty($toolCalls)) {
                $toolCallObjects = array_map(function ($toolCall) {
                    return ToolCall::fromArray($toolCall);
                }, array_values($toolCalls));

                // Execute tool calls and add their responses
                if (! empty($toolCallObjects)) {
                    $results = $this->toolExecutor->executeMany($toolCallObjects);

                    foreach ($results as $toolCallId => $result) {
                        $resultContent = is_string($result) ? $result : json_encode($result);
                        $this->messages[] = new Message(Role::TOOL, $resultContent, null, $toolCallId);
                    }
                }

                $this->messages[] = new Message(Role::ASSISTANT, $fullContent, $toolCallObjects);
            }

            return $fullContent;
        } catch (\Exception $e) {
            $this->eventHandler->trigger('error', $e);

            throw $e;
        }
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
     * Clear all messages from the conversation.
     */
    public function clearMessages(): self
    {
        $this->messages = [];

        return $this;
    }

    /**
     * Get the model being used by the conversation.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the tool registry.
     */
    public function getToolRegistry(): ToolRegistry
    {
        return $this->toolRegistry;
    }

    /**
     * Get the event handler.
     */
    public function getEventHandler(): EventHandler
    {
        return $this->eventHandler;
    }

    /**
     * Check if streaming is enabled.
     */
    public function isStreaming(): bool
    {
        return $this->streaming;
    }

    /**
     * Get the conversation options.
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
