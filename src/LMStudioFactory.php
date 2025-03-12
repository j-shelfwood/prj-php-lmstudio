<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio;

use Shelfwood\LMStudio\Api\Client\ApiClient;
use Shelfwood\LMStudio\Api\Client\HttpClient;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Api\Service\CompletionService;
use Shelfwood\LMStudio\Api\Service\EmbeddingService;
use Shelfwood\LMStudio\Api\Service\ModelService;
use Shelfwood\LMStudio\Core\Builder\ConversationBuilder;
use Shelfwood\LMStudio\Core\Conversation\Conversation;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;

class LMStudioFactory
{
    private string $baseUrl;

    private array $defaultHeaders;

    /**
     * @param  string  $baseUrl  The base URL of the API
     * @param  array  $defaultHeaders  Default headers to include in all requests
     */
    public function __construct(
        string $baseUrl = 'http://localhost:1234',
        array $defaultHeaders = []
    ) {
        $this->baseUrl = $baseUrl;
        $this->defaultHeaders = $defaultHeaders;
    }

    /**
     * Create an API client.
     */
    public function createApiClient(): ApiClient
    {
        return new ApiClient(
            new HttpClient,
            $this->baseUrl,
            $this->defaultHeaders
        );
    }

    /**
     * Create a model service.
     */
    public function createModelService(): ModelService
    {
        return new ModelService($this->createApiClient());
    }

    /**
     * Create a chat service.
     */
    public function createChatService(): ChatService
    {
        return new ChatService($this->createApiClient());
    }

    /**
     * Create a completion service.
     */
    public function createCompletionService(): CompletionService
    {
        return new CompletionService($this->createApiClient());
    }

    /**
     * Create an embedding service.
     */
    public function createEmbeddingService(): EmbeddingService
    {
        return new EmbeddingService($this->createApiClient());
    }

    /**
     * Create a conversation.
     *
     * @param  string  $model  The model to use
     * @param  array  $options  Additional options
     * @param  ToolRegistry|null  $toolRegistry  The tool registry
     * @param  EventHandler|null  $eventHandler  The event handler
     * @param  bool  $streaming  Whether to enable streaming
     */
    public function createConversation(
        string $model,
        array $options = [],
        ?ToolRegistry $toolRegistry = null,
        ?EventHandler $eventHandler = null,
        bool $streaming = false
    ): Conversation {
        return new Conversation(
            $this->createChatService(),
            $model,
            $options,
            $toolRegistry,
            $eventHandler,
            $streaming
        );
    }

    /**
     * Create a conversation builder.
     *
     * @param  string  $model  The model to use
     */
    public function createConversationBuilder(string $model): ConversationBuilder
    {
        return new ConversationBuilder(
            $this->createChatService(),
            $model
        );
    }
}
