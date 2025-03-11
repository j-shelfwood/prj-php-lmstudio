<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Conversations;

use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;

/**
 * Manages conversation operations.
 */
class ConversationManager
{
    private LMStudioClientInterface $client;

    /**
     * Create a new conversation manager.
     */
    public function __construct(LMStudioClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Create a new conversation.
     */
    public function createConversation(string $title = 'New Conversation'): Conversation
    {
        return Conversation::builder($this->client)
            ->withTitle($title)
            ->build();
    }

    /**
     * Create a conversation with a system message.
     */
    public function createConversationWithSystem(string $systemMessage, string $title = 'New Conversation'): Conversation
    {
        return Conversation::builder($this->client)
            ->withTitle($title)
            ->withSystemMessage($systemMessage)
            ->build();
    }

    /**
     * Create a conversation with tools.
     */
    public function createConversationWithTools(
        ToolRegistry $toolRegistry,
        string $title = 'New Conversation',
        ?string $systemMessage = null
    ): Conversation {
        $builder = Conversation::builder($this->client)
            ->withTitle($title)
            ->withToolRegistry($toolRegistry);

        if ($systemMessage !== null) {
            $builder = $builder->withSystemMessage($systemMessage);
        }

        return $builder->build();
    }

    /**
     * Load a conversation from JSON.
     */
    public function loadConversation(string $json): Conversation
    {
        return Conversation::fromJson($json, $this->client);
    }

    /**
     * Save a conversation to JSON.
     */
    public function saveConversation(Conversation $conversation): string
    {
        return $conversation->toJson();
    }

    /**
     * Create a conversation from a chat history.
     */
    public function createFromHistory(
        ChatHistory $history,
        string $title = 'New Conversation',
        ?ToolRegistry $toolRegistry = null
    ): Conversation {
        $builder = Conversation::builder($this->client)
            ->withTitle($title)
            ->withHistory($history);

        if ($toolRegistry !== null) {
            $builder = $builder->withToolRegistry($toolRegistry);
        }

        return $builder->build();
    }

    /**
     * Get the client used by the manager.
     */
    public function getClient(): LMStudioClientInterface
    {
        return $this->client;
    }
}
