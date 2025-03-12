<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Conversations;

use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Http\Factories\RequestFactoryInterface;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;

/**
 * Manages conversation operations.
 */
class ConversationManager implements ConversationManagerInterface
{
    private LMStudioClientInterface $client;

    private ?RequestFactoryInterface $requestFactory;

    /**
     * Create a new conversation manager.
     */
    public function __construct(
        LMStudioClientInterface $client,
        ?RequestFactoryInterface $requestFactory = null
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
    }

    /**
     * Create a new conversation.
     */
    public function createConversation(string $title = 'New Conversation'): ConversationInterface
    {
        return Conversation::builder($this->client, $this->requestFactory)
            ->withTitle($title)
            ->build();
    }

    /**
     * Create a conversation with a system message.
     */
    public function createConversationWithSystem(string $systemMessage, string $title = 'New Conversation'): ConversationInterface
    {
        return Conversation::builder($this->client, $this->requestFactory)
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
    ): ConversationInterface {
        $builder = Conversation::builder($this->client, $this->requestFactory)
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
    public function loadConversation(string $json): ConversationInterface
    {
        return Conversation::fromJson($json, $this->client, $this->requestFactory);
    }

    /**
     * Save a conversation to JSON.
     */
    public function saveConversation(ConversationInterface $conversation): string
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
    ): ConversationInterface {
        $builder = Conversation::builder($this->client, $this->requestFactory)
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
