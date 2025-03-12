<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Conversation;

use Shelfwood\LMStudio\Contract\ConversationInterface;
use Shelfwood\LMStudio\Enum\Role;
use Shelfwood\LMStudio\Exception\ApiException;
use Shelfwood\LMStudio\Model\Message;
use Shelfwood\LMStudio\Service\ChatService;

class Conversation implements ConversationInterface
{
    private ChatService $chatService;

    private string $model;

    private array $messages = [];

    private array $options;

    /**
     * @param  ChatService  $chatService  The chat service
     * @param  string  $model  The model to use
     * @param  array  $options  Additional options
     */
    public function __construct(
        ChatService $chatService,
        string $model,
        array $options = []
    ) {
        $this->chatService = $chatService;
        $this->model = $model;
        $this->options = $options;
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
        $completion = $this->chatService->createCompletion(
            $this->model,
            $this->messages,
            $this->options
        );

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

        return $content ?? '';
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
}
