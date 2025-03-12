<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Conversation;

use Shelfwood\LMStudio\Api\Contract\ConversationInterface;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Exception\ApiException;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Event\EventHandler;
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

    /**
     * @param  ChatService  $chatService  The chat service
     * @param  string  $model  The model to use
     * @param  array  $options  Additional options
     * @param  ToolRegistry|null  $toolRegistry  The tool registry
     * @param  EventHandler|null  $eventHandler  The event handler
     * @param  bool  $streaming  Whether to enable streaming
     */
    public function __construct(
        ChatService $chatService,
        string $model,
        array $options = [],
        ?ToolRegistry $toolRegistry = null,
        ?EventHandler $eventHandler = null,
        bool $streaming = false
    ) {
        $this->chatService = $chatService;
        $this->model = $model;
        $this->options = $options;
        $this->toolRegistry = $toolRegistry ?? new ToolRegistry;
        $this->eventHandler = $eventHandler ?? new EventHandler;
        $this->streaming = $streaming;

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
            $completion = $this->chatService->createCompletion(
                $this->model,
                $this->messages,
                $this->options
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
                $this->messages[] = new Message(Role::ASSISTANT, $content ?? '', $toolCalls);
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
     * @param  callable  $callback  The callback function to call for each chunk
     * @return string The complete response
     *
     * @throws ApiException If the request fails
     */
    public function getStreamingResponse(callable $callback): string
    {
        if (! $this->streaming) {
            $this->options['stream'] = true;
            $this->streaming = true;
        }

        try {
            $fullContent = '';
            $toolCalls = null;

            $this->chatService->createCompletionStream(
                $this->model,
                $this->messages,
                $this->options,
                function ($chunk) use (&$fullContent, &$toolCalls, $callback): void {
                    $this->eventHandler->trigger('chunk', $chunk);
                    $callback($chunk);

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
                $this->messages[] = new Message(Role::ASSISTANT, $fullContent, $toolCalls);
            }

            // Handle tool calls if present
            if (! empty($toolCalls)) {
                $this->handleToolCalls($toolCalls);
            }

            return $fullContent;
        } catch (\Exception $e) {
            $this->eventHandler->trigger('error', $e);

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
            $toolCallId = $toolCall['id'] ?? '';
            $functionName = $toolCall['function']['name'] ?? '';
            $arguments = $toolCall['function']['arguments'] ?? '{}';

            // Parse arguments
            $parsedArguments = json_decode($arguments, true) ?? [];

            $this->eventHandler->trigger('tool_call', $functionName, $parsedArguments, $toolCallId);

            // Execute the tool if registered
            if ($this->toolRegistry->hasTool($functionName)) {
                try {
                    $result = $this->toolRegistry->executeTool($functionName, $parsedArguments);
                    $resultContent = is_string($result) ? $result : json_encode($result);

                    // Add the tool response to the conversation
                    $this->addToolMessage($resultContent, $toolCallId);
                } catch (\Exception $e) {
                    $this->eventHandler->trigger('error', $e);
                    $this->addToolMessage("Error: {$e->getMessage()}", $toolCallId);
                }
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
}
