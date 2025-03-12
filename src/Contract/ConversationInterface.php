<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Contract;

use Shelfwood\LMStudio\Model\Message;

interface ConversationInterface
{
    /**
     * Add a system message to the conversation.
     *
     * @param string $content The message content
     * @return self
     */
    public function addSystemMessage(string $content): self;

    /**
     * Add a user message to the conversation.
     *
     * @param string $content The message content
     * @return self
     */
    public function addUserMessage(string $content): self;

    /**
     * Get a response from the model.
     *
     * @return string The model's response
     */
    public function getResponse(): string;

    /**
     * Get all messages in the conversation.
     *
     * @return array<Message> The messages
     */
    public function getMessages(): array;

    /**
     * Clear all messages in the conversation.
     *
     * @return self
     */
    public function clearMessages(): self;
}