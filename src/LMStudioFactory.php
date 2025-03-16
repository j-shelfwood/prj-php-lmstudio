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
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\Core\Tool\ToolService;

class LMStudioFactory
{
    private string $baseUrl;

    private array $defaultHeaders;

    /**
     * @var string The API key for LMStudio
     */
    protected string $apiKey;

    /**
     * @var HttpClient|null The HTTP client instance
     */
    protected ?HttpClient $httpClient = null;

    /**
     * @var ApiClient|null The API client instance
     */
    protected ?ApiClient $apiClient = null;

    /**
     * @var ModelService|null The model service instance
     */
    protected ?ModelService $modelService = null;

    /**
     * @var ChatService|null The chat service instance
     */
    protected ?ChatService $chatService = null;

    /**
     * @var ToolService|null The tool service instance
     */
    protected ?ToolService $toolService = null;

    /**
     * @var ToolExecutor|null The tool executor instance
     */
    protected ?ToolExecutor $toolExecutor = null;

    /**
     * @param  string  $baseUrl  The base URL of the API
     * @param  array  $defaultHeaders  Default headers to include in all requests
     */
    public function __construct(
        string $baseUrl,
        array $defaultHeaders,
        string $apiKey
    ) {
        $this->baseUrl = $baseUrl;
        $this->defaultHeaders = $defaultHeaders;
        $this->apiKey = $apiKey;
    }

    /**
     * Get the HTTP client instance.
     *
     * @return HttpClient The HTTP client instance
     */
    public function getHttpClient(): HttpClient
    {
        if ($this->httpClient === null) {
            $this->httpClient = new HttpClient($this->baseUrl, $this->defaultHeaders);
        }

        return $this->httpClient;
    }

    /**
     * Get the API client instance.
     *
     * @return ApiClient The API client instance
     */
    public function getApiClient(): ApiClient
    {
        if ($this->apiClient === null) {
            $this->apiClient = $this->createApiClient();
        }

        return $this->apiClient;
    }

    /**
     * Get the model service instance.
     *
     * @return ModelService The model service instance
     */
    public function getModelService(): ModelService
    {
        if ($this->modelService === null) {
            $this->modelService = $this->createModelService();
        }

        return $this->modelService;
    }

    /**
     * Get the chat service instance.
     *
     * @return ChatService The chat service instance
     */
    public function getChatService(): ChatService
    {
        if ($this->chatService === null) {
            $this->chatService = $this->createChatService();
        }

        return $this->chatService;
    }

    /**
     * Get the tool service instance.
     */
    public function getToolService(): ToolService
    {
        if ($this->toolService === null) {
            $this->toolService = $this->createToolService();
        }

        return $this->toolService;
    }

    /**
     * Get the tool executor instance.
     */
    public function getToolExecutor(): ToolExecutor
    {
        if ($this->toolExecutor === null) {
            $this->toolExecutor = $this->createToolExecutor();
        }

        return $this->toolExecutor;
    }

    /**
     * Create a tool service instance.
     */
    public function createToolService(): ToolService
    {
        return new ToolService(
            new ToolRegistry,
            [] // Empty initial configurations
        );
    }

    /**
     * Create a tool executor instance.
     */
    public function createToolExecutor(): ToolExecutor
    {
        return new ToolExecutor(
            $this->getToolService()->getToolRegistry(),
            $this->createEventHandler()
        );
    }

    /**
     * Create an API client.
     */
    public function createApiClient(): ApiClient
    {
        return new ApiClient(
            $this->getHttpClient(),
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
        $eventHandler = $eventHandler ?? $this->createEventHandler();
        $toolRegistry = $toolRegistry ?? $this->getToolService()->getToolRegistry();

        return new Conversation(
            $this->getChatService(),
            $model,
            $options,
            $toolRegistry,
            $eventHandler,
            $streaming,
            $streaming ? $this->createStreamingHandler() : null,
            new ToolExecutor($toolRegistry, $eventHandler)
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
            $this->getChatService(),
            $model
        );
    }

    /**
     * Create a queueable conversation builder.
     *
     * @param  string  $model  The model to use
     * @param  bool|null  $queueToolsByDefault  Whether to queue tool executions by default
     */
    public function createQueueableConversationBuilder(string $model, ?bool $queueToolsByDefault = null): \Shelfwood\LMStudio\Laravel\Conversation\QueueableConversationBuilder
    {
        if (! class_exists(\Shelfwood\LMStudio\Laravel\Conversation\QueueableConversationBuilder::class)) {
            throw new \RuntimeException('Laravel integration is not available. Make sure you have the Laravel package installed.');
        }

        return new \Shelfwood\LMStudio\Laravel\Conversation\QueueableConversationBuilder(
            $this->getChatService(),
            $model,
            $queueToolsByDefault
        );
    }

    /**
     * Create a new streaming conversation instance.
     *
     * @param  string  $model  The model to use
     * @param  array  $options  The options for the conversation
     * @return Conversation The streaming conversation instance
     */
    public function createStreamingConversation(string $model, array $options = []): Conversation
    {
        $options['stream'] = true;

        return new Conversation($this->getChatService(), $model, $options);
    }

    /**
     * Create a new streaming conversation builder instance.
     *
     * @param  string  $model  The model to use
     * @return ConversationBuilder The streaming conversation builder instance
     */
    public function createStreamingConversationBuilder(string $model): ConversationBuilder
    {
        return (new ConversationBuilder($this->getChatService(), $model))->withStreaming();
    }

    /**
     * Create a streaming handler.
     */
    public function createStreamingHandler(): StreamingHandler
    {
        return new StreamingHandler;
    }

    /**
     * Create an event handler.
     */
    protected function createEventHandler(): EventHandler
    {
        return new EventHandler;
    }

    /**
     * Create a new Laravel streaming handler instance.
     *
     * @return \Shelfwood\LMStudio\Laravel\Streaming\LaravelStreamingHandler The Laravel streaming handler instance
     *
     * @throws RuntimeException If the Laravel package is not installed
     */
    public function createLaravelStreamingHandler(): object
    {
        if (! class_exists('Shelfwood\LMStudio\Laravel\Streaming\LaravelStreamingHandler')) {
            throw new \RuntimeException('The Laravel package must be installed to use this method.');
        }

        return new \Shelfwood\LMStudio\Laravel\Streaming\LaravelStreamingHandler;
    }
}
