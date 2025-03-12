<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Chat;

use Shelfwood\LMStudio\Api\Contract\LMStudioClientInterface;
use Shelfwood\LMStudio\Tool\ToolRegistry;
use Shelfwood\LMStudio\ValueObject\ChatHistory;

/**
 * Interface for conversation managers.
 */
interface ConversationManagerInterface
{
    /**
     * Create a new conversation.
     */
    public function createConversation(string $title = 'New Conversation'): ConversationInterface;

    /**
     * Create a conversation with a system message.
     */
    public function createConversationWithSystem(string $systemMessage, string $title = 'New Conversation'): ConversationInterface;

    /**
     * Create a conversation with tools.
     */
    public function createConversationWithTools(
        ToolRegistry $toolRegistry,
        string $title = 'New Conversation',
        ?string $systemMessage = null
    ): ConversationInterface;

    /**
     * Load a conversation from JSON.
     */
    public function loadConversation(string $json): ConversationInterface;

    /**
     * Save a conversation to JSON.
     */
    public function saveConversation(ConversationInterface $conversation): string;

    /**
     * Create a conversation from a chat history.
     */
    public function createFromHistory(
        ChatHistory $history,
        string $title = 'New Conversation',
        ?ToolRegistry $toolRegistry = null
    ): ConversationInterface;

    /**
     * Get the client used by the manager.
     */
    public function getClient(): LMStudioClientInterface;
}
