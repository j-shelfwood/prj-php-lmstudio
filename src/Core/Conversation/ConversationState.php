<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Conversation;

use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Core\Contract\ConversationStateInterface;

/**
 * Holds the immutable configuration and mutable state of a single conversation thread.
 * Implements ConversationStateInterface.
 */
class ConversationState implements ConversationStateInterface
{
    /**
     * @param  string  $model  The model ID for the conversation.
     * @param  array<string, mixed>  $options  Initial configuration options (e.g., temperature). Should not include 'stream'.
     * @param  list<Message>  $initialMessages  Pre-existing messages to start the conversation with.
     */
    public function __construct(
        public readonly string $model,
        public readonly array $options,
        private array $messages = []
    ) {}

    /**
     * Add a single message to the conversation history.
     *
     * @param  Message  $message  The message to add.
     */
    public function addMessage(Message $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * Add multiple messages to the conversation history.
     *
     * @param  list<Message>  $messages  An array of Message objects.
     */
    public function addMessages(array $messages): void
    {
        foreach ($messages as $message) {
            if ($message instanceof Message) {
                $this->messages[] = $message;
            }
        }
    }

    /**
     * Add a user message to the conversation history.
     *
     * @param  string  $content  The text content of the user message.
     */
    public function addUserMessage(string $content): void
    {
        $this->addMessage(new Message(Role::USER, $content));
    }

    /**
     * Add an assistant message to the conversation history.
     *
     * @param  string  $content  The text content of the assistant message.
     * @param  list<\Shelfwood\LMStudio\Api\Model\Tool\ToolCall>|null  $toolCalls  Optional tool calls associated with the message.
     */
    public function addAssistantMessage(string $content, ?array $toolCalls = null): void
    {
        $this->addMessage(new Message(Role::ASSISTANT, $content, $toolCalls));
    }

    /**
     * Add a tool message (result) to the conversation history.
     *
     * @param  string  $toolCallId  The ID of the tool call this message is a result for.
     * @param  string  $content  The content of the tool result.
     */
    public function addToolMessage(string $toolCallId, string $content): void
    {
        $this->addMessage(Message::forToolResponse($content, $toolCallId));
    }

    /**
     * Add a system message to the conversation history.
     *
     * @param  string  $content  The text content of the system message.
     */
    public function addSystemMessage(string $content): void
    {
        $this->addMessage(new Message(Role::SYSTEM, $content));
    }

    /**
     * Get all messages currently in the conversation history.
     *
     * @return list<Message>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Clear all messages from the conversation history.
     */
    public function clearMessages(): void
    {
        $this->messages = [];
    }

    /**
     * Get the model ID being used for the conversation.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the initial options configured for the conversation.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get the last message added to the conversation history.
     *
     * @return Message|null Null if the history is empty.
     */
    public function getLastMessage(): ?Message
    {
        $count = count($this->messages);

        return $count > 0 ? $this->messages[$count - 1] : null;
    }

    /**
     * Set the entire message history.
     * Primarily intended for internal use by Turn Handlers if necessary.
     *
     * @param  list<Message>  $messages  The complete list of messages to set.
     */
    public function setMessages(array $messages): void
    {
        // Basic validation to ensure it's a list of Message objects
        $this->messages = array_filter($messages, fn ($m) => $m instanceof Message);
    }
}
