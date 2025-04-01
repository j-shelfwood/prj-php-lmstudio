<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Contract;

use Shelfwood\LMStudio\Api\Model\Message;

/**
 * Defines the contract for accessing the state of a conversation.
 */
interface ConversationStateInterface
{
    /**
     * Get the model ID being used for the conversation.
     */
    public function getModel(): string;

    /**
     * Get the initial options configured for the conversation.
     * These typically exclude dynamic options like 'stream'.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array;

    /**
     * Get the current list of messages in the conversation history.
     *
     * @return list<Message>
     */
    public function getMessages(): array;

    /**
     * Get the last message added to the conversation history.
     *
     * @return Message|null Null if the history is empty.
     */
    public function getLastMessage(): ?Message;
}
