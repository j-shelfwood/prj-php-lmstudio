<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio;

use Shelfwood\LMStudio\Api\ApiClient;
use Shelfwood\LMStudio\Api\HttpClient;
use Shelfwood\LMStudio\Conversation\Conversation;
use Shelfwood\LMStudio\Service\ChatService;
use Shelfwood\LMStudio\Service\CompletionService;
use Shelfwood\LMStudio\Service\EmbeddingService;
use Shelfwood\LMStudio\Service\ModelService;

class LMStudioFactory
{
    private string $baseUrl;
    private array $defaultHeaders;

    /**
     * @param string $baseUrl The base URL of the API
     * @param array $defaultHeaders Default headers to include in all requests
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
     *
     * @return ApiClient
     */
    public function createApiClient(): ApiClient
    {
        return new ApiClient(
            new HttpClient(),
            $this->baseUrl,
            $this->defaultHeaders
        );
    }

    /**
     * Create a model service.
     *
     * @return ModelService
     */
    public function createModelService(): ModelService
    {
        return new ModelService($this->createApiClient());
    }

    /**
     * Create a chat service.
     *
     * @return ChatService
     */
    public function createChatService(): ChatService
    {
        return new ChatService($this->createApiClient());
    }

    /**
     * Create a completion service.
     *
     * @return CompletionService
     */
    public function createCompletionService(): CompletionService
    {
        return new CompletionService($this->createApiClient());
    }

    /**
     * Create an embedding service.
     *
     * @return EmbeddingService
     */
    public function createEmbeddingService(): EmbeddingService
    {
        return new EmbeddingService($this->createApiClient());
    }

    /**
     * Create a conversation.
     *
     * @param string $model The model to use
     * @param array $options Additional options
     * @return Conversation
     */
    public function createConversation(string $model, array $options = []): Conversation
    {
        return new Conversation(
            $this->createChatService(),
            $model,
            $options
        );
    }
}