<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Conversations;

use Shelfwood\LMStudio\Streaming\StreamBuilderInterface;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;

/**
 * Interface for conversations.
 */
interface ConversationInterface
{
    /**
     * Add a system message to the conversation.
     */
    public function addSystemMessage(string $content): self;

    /**
     * Add a user message to the conversation.
     */
    public function addUserMessage(string $content, ?string $name = null): self;

    /**
     * Add an assistant message to the conversation.
     */
    public function addAssistantMessage(string $content, array $toolCalls = []): self;

    /**
     * Add a tool message to the conversation.
     */
    public function addToolMessage(string $content, string $toolCallId): self;

    /**
     * Set the model for the conversation.
     */
    public function setModel(string $model): self;

    /**
     * Set the temperature for the conversation.
     */
    public function setTemperature(float $temperature): self;

    /**
     * Set the max tokens for the conversation.
     */
    public function setMaxTokens(int $maxTokens): self;

    /**
     * Set the tool registry for the conversation.
     */
    public function setToolRegistry(ToolRegistry $toolRegistry): self;

    /**
     * Get the chat history.
     */
    public function getChatHistory(): ChatHistory;

    /**
     * Get all messages in the conversation.
     */
    public function getMessages(): array;

    /**
     * Get the last message in the conversation.
     */
    public function getLastMessage(): ?Message;

    /**
     * Get the conversation ID.
     */
    public function getId(): string;

    /**
     * Get the conversation title.
     */
    public function getTitle(): string;

    /**
     * Set the conversation title.
     */
    public function setTitle(string $title): self;

    /**
     * Get the model.
     */
    public function getModel(): ?string;

    /**
     * Get the temperature.
     */
    public function getTemperature(): float;

    /**
     * Get the max tokens.
     */
    public function getMaxTokens(): ?int;

    /**
     * Create a stream builder for this conversation.
     */
    public function stream(): StreamBuilderInterface;

    /**
     * Send a message and get the response.
     */
    public function send(string $message, ?string $name = null): string;

    /**
     * Convert the conversation to JSON.
     */
    public function toJson(): string;
}
