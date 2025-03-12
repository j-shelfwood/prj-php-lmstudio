<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObject;

/**
 * Represents a chat history.
 *
 * @implements \IteratorAggregate<int, Message>
 */
class ChatHistory implements \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * @var array<Message> The messages in the chat history
     */
    private array $messages = [];

    /**
     * Create a new chat history.
     *
     * @param  array<Message>  $messages  The initial messages
     */
    public function __construct(array $messages = [])
    {
        foreach ($messages as $message) {
            $this->addMessage($message);
        }
    }

    /**
     * Create a chat history from an array of message data.
     *
     * @param array $messagesData Array of message data
     * @return self
     */
    public static function fromArray(array $messagesData): self
    {
        $history = new self();

        foreach ($messagesData as $messageData) {
            if ($messageData instanceof Message) {
                $history->addMessage($messageData);
            } else {
                $message = Message::fromArray($messageData);
                $history->addMessage($message);
            }
        }

        return $history;
    }

    /**
     * Add a message to the chat history.
     */
    public function addMessage(Message $message): self
    {
        $this->messages[] = $message;

        return $this;
    }

    /**
     * Add a system message to the chat history.
     */
    public function addSystemMessage(string $content): self
    {
        return $this->addMessage(Message::system($content));
    }

    /**
     * Add a user message to the chat history.
     */
    public function addUserMessage(string $content, ?string $name = null): self
    {
        return $this->addMessage(Message::user($content, $name));
    }

    /**
     * Add an assistant message to the chat history.
     */
    public function addAssistantMessage(string $content, ?array $toolCalls = null): self
    {
        return $this->addMessage(Message::assistant($content, $toolCalls));
    }

    /**
     * Add a tool message to the chat history.
     */
    public function addToolMessage(string $content, string $toolCallId): self
    {
        return $this->addMessage(Message::tool($content, $toolCallId));
    }

    /**
     * Get all messages in the chat history.
     *
     * @return array<Message>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the last message in the chat history.
     */
    public function getLastMessage(): ?Message
    {
        if (empty($this->messages)) {
            return null;
        }

        return $this->messages[count($this->messages) - 1];
    }

    /**
     * Clear the chat history.
     */
    public function clear(): self
    {
        $this->messages = [];

        return $this;
    }

    /**
     * Count the number of messages in the chat history.
     */
    public function count(): int
    {
        return count($this->messages);
    }

    /**
     * Get an iterator for the messages.
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->messages);
    }

    /**
     * Convert the chat history to an array.
     */
    public function jsonSerialize(): array
    {
        return array_map(
            fn (Message $message) => $message->jsonSerialize(),
            $this->messages
        );
    }
}
