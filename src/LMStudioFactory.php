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
use Shelfwood\LMStudio\Core\Tool\ToolConfigService;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

// Define constants for Laravel classes if they exist, otherwise null
// This avoids hard errors if Laravel integration isn't used/installed.
if (! defined('LARAVEL_QUEUEABLE_BUILDER_CLASS')) {
    define('LARAVEL_QUEUEABLE_BUILDER_CLASS', class_exists('Shelfwood\\LMStudio\\Laravel\\Conversation\\QueueableConversationBuilder') ? 'Shelfwood\\LMStudio\\Laravel\\Conversation\\QueueableConversationBuilder' : null);
}

if (! defined('LARAVEL_STREAMING_HANDLER_CLASS')) {
    define('LARAVEL_STREAMING_HANDLER_CLASS', class_exists('Shelfwood\\LMStudio\\Laravel\\Core\\Streaming\\LaravelStreamingHandler') ? 'Shelfwood\\LMStudio\\Laravel\\Core\\Streaming\\LaravelStreamingHandler' : null);
}

class LMStudioFactory
{
    public readonly string $baseUrl;

    public readonly array $defaultHeaders;

    protected readonly string $apiKey;

    // Single instances of core components
    protected ?HttpClient $httpClient = null;

    protected ?ApiClient $apiClient = null;

    protected ?ModelService $modelService = null;

    protected ?ChatService $chatService = null;

    protected ?CompletionService $completionService = null;

    protected ?EmbeddingService $embeddingService = null;

    public readonly ToolRegistry $toolRegistry;

    public readonly EventHandler $eventHandler;

    public readonly ToolExecutor $toolExecutor;

    public readonly ToolConfigService $toolConfigService;

    // Cache for ExpressionLanguage
    private ?ExpressionLanguage $expressionLanguage = null;

    /**
     * @param  string  $baseUrl  The base URL of the API
     * @param  array  $defaultHeaders  Default headers to include in all requests
     * @param  string  $apiKey  The API key for LMStudio
     */
    public function __construct(
        string $baseUrl,
        array $defaultHeaders,
        string $apiKey
    ) {
        $this->baseUrl = $baseUrl;
        $this->defaultHeaders = $defaultHeaders;
        $this->apiKey = $apiKey;

        // Initialize core components ONCE using public readonly properties
        $this->toolRegistry = new ToolRegistry;
        $this->eventHandler = $this->createEventHandler();
        $this->toolExecutor = new ToolExecutor($this->toolRegistry, $this->eventHandler);
        $this->toolConfigService = $this->createToolConfigService(
            $this->toolRegistry,
            $this->toolExecutor,
            $this->eventHandler
        );
    }

    /**
     * Get the HTTP client instance. Lazy loaded.
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
     * Get the API client instance. Lazy loaded.
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
     * Get the model service instance. Lazy loaded.
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
     * Get the chat service instance. Lazy loaded.
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
     * Get the completion service instance. Lazy loaded.
     *
     * @return CompletionService The completion service instance
     */
    public function getCompletionService(): CompletionService
    {
        if ($this->completionService === null) {
            $this->completionService = $this->createCompletionService();
        }

        return $this->completionService;
    }

    /**
     * Get the embedding service instance. Lazy loaded.
     *
     * @return EmbeddingService The embedding service instance
     */
    public function getEmbeddingService(): EmbeddingService
    {
        if ($this->embeddingService === null) {
            $this->embeddingService = $this->createEmbeddingService();
        }

        return $this->embeddingService;
    }

    /**
     * Create the single tool config service instance.
     * Called only from the constructor.
     */
    protected function createToolConfigService(
        ToolRegistry $toolRegistry,
        ToolExecutor $toolExecutor,
        EventHandler $eventHandler // Kept parameter for consistency
    ): ToolConfigService {
        // Define standard tools here
        $toolConfigurations = [
            // Echo tool
            'echo' => [
                'callback' => function (array $args) {
                    return $args['message'] ?? 'No message provided';
                },
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => [
                            'type' => 'string',
                            'description' => 'The message to echo back',
                        ],
                    ],
                    'required' => ['message'],
                ],
                'description' => 'Echoes back the message provided',
            ],
            // Current time tool
            'get_current_time' => [
                'callback' => function () {
                    return date('Y-m-d H:i:s');
                },
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
                'description' => 'Get the current server time. Use this whenever asked about the current time.',
            ],
            // Calculator tool - SAFE IMPLEMENTATION
            'calculate' => [
                'callback' => function (array $args) {
                    $expression = $args['expression'] ?? '';

                    if (empty($expression)) {
                        throw new \InvalidArgumentException('Mathematical expression cannot be empty.');
                    }

                    if ($this->expressionLanguage === null) {
                        $this->expressionLanguage = new ExpressionLanguage(null, [
                            // Providers can be added here if needed
                        ]);
                    }

                    try {
                        $result = $this->expressionLanguage->evaluate($expression);

                        if (! is_scalar($result) && $result !== null) {
                            throw new \RuntimeException('Calculation result is not a scalar value.');
                        }

                        return [
                            'expression' => $expression,
                            'result' => $result,
                        ];
                    } catch (\Symfony\Component\ExpressionLanguage\SyntaxError $e) {
                        throw new \InvalidArgumentException('Syntax error in expression: '.$e->getMessage());
                    } catch (\Exception $e) {
                        throw new \InvalidArgumentException('Error evaluating expression: '.$e->getMessage());
                    }
                },
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'expression' => [
                            'type' => 'string',
                            'description' => 'The mathematical expression to evaluate (e.g., "2 + 2 * (3/4)"). Supports basic arithmetic operators +, -, *, / and parentheses.',
                        ],
                    ],
                    'required' => ['expression'],
                ],
                'description' => 'Calculate the result of a mathematical expression.',
            ],
        ];

        // Pass pre-created instances; EventHandler is passed but might not be used by ToolConfigService directly
        return new ToolConfigService($toolRegistry, $toolExecutor, $toolConfigurations, $eventHandler);
    }

    /**
     * Create an API client instance.
     *
     * @internal Used by service creation methods.
     */
    protected function createApiClient(): ApiClient
    {
        return new ApiClient($this->getHttpClient(), $this->baseUrl, $this->defaultHeaders);
    }

    /**
     * Create a model service instance.
     *
     * @internal Used by getModelService.
     */
    protected function createModelService(): ModelService
    {
        return new ModelService($this->getApiClient());
    }

    /**
     * Create a chat service instance.
     *
     * @internal Used by getChatService.
     */
    protected function createChatService(): ChatService
    {
        return new ChatService($this->getApiClient());
    }

    /**
     * Create a completion service instance.
     *
     * @internal Use getCompletionService() for external access.
     */
    protected function createCompletionService(): CompletionService
    {
        return new CompletionService($this->getApiClient());
    }

    /**
     * Create an embedding service instance.
     *
     * @internal Use getEmbeddingService() for external access.
     */
    protected function createEmbeddingService(): EmbeddingService
    {
        return new EmbeddingService($this->getApiClient());
    }

    /**
     * Create a new conversation instance.
     *
     * @param  string  $model  The model ID to use for the conversation.
     * @param  array  $options  Additional options for the conversation (e.g., temperature).
     * @param  ToolRegistry|null  $toolRegistry  Optional specific ToolRegistry. Defaults to the factory's configured one.
     * @param  EventHandler|null  $eventHandler  Optional specific EventHandler. Defaults to the factory's one.
     * @param  bool  $streaming  Whether to enable streaming mode.
     * @return Conversation The created conversation instance.
     */
    public function createConversation(
        string $model,
        array $options = [],
        ?ToolRegistry $toolRegistry = null,
        ?EventHandler $eventHandler = null,
        bool $streaming = false
    ): Conversation {
        // Ensure the correct singletons are used if specific ones aren't provided
        $registry = $toolRegistry ?? $this->toolRegistry;
        $handler = $eventHandler ?? $this->eventHandler;
        $executor = $this->toolExecutor; // Always use the factory's executor
        $streamingHandler = null;

        // Prepare options, adding stream=true if needed
        $finalOptions = $options;

        if ($streaming) {
            $streamingHandler = $this->createStreamingHandler($handler);
            // Ensure stream option is explicitly set for the API call
            $finalOptions['stream'] = true;
        }

        // Pass the correct dependencies to the Conversation
        return new Conversation(
            $this->getChatService(), // Pass the ChatService instance
            $model,
            $finalOptions, // Use the potentially modified options
            $registry,
            $handler,
            $streaming, // Pass the boolean flag
            $streamingHandler, // Pass the created handler or null
            $executor
        );
    }

    /**
     * Create a new conversation builder instance.
     *
     * @param  string  $model  The model ID to use for the conversation.
     * @return ConversationBuilder The created conversation builder instance.
     */
    public function createConversationBuilder(string $model): ConversationBuilder
    {
        $builder = new ConversationBuilder($this->getChatService(), $model);

        // Inject components using public readonly properties
        $builder->withToolRegistry($this->toolRegistry)
            ->withToolExecutor($this->toolExecutor);

        return $builder;
    }

    /**
     * Create a new queueable conversation builder instance (Requires Laravel context).
     *
     * @param  string  $model  The model ID to use.
     * @param  bool|null  $queueToolsByDefault  Whether tools should be queued by default.
     * @return object|null An instance of QueueableConversationBuilder or null if class doesn't exist.
     *
     * @throws \RuntimeException If the required Laravel class is not available.
     */
    public function createQueueableConversationBuilder(string $model, ?bool $queueToolsByDefault = null): ?object
    {
        $builderClass = LARAVEL_QUEUEABLE_BUILDER_CLASS;

        if ($builderClass === null) {
            throw new \RuntimeException('QueueableConversationBuilder requires Laravel integration classes.');
        }
        $builder = new $builderClass($this->getChatService(), $model);

        // Assume builder has these methods, inject using public readonly properties
        $builder->withToolRegistry($this->toolRegistry)
            ->withToolExecutor($this->toolExecutor);

        if (method_exists($builder, 'queueToolsByDefault') && $queueToolsByDefault !== null) {
            $builder->queueToolsByDefault($queueToolsByDefault);
        }

        return $builder;
    }

    /**
     * Create a streaming conversation instance.
     *
     * @param  string  $model  The model to use for the conversation.
     * @param  array  $options  Optional parameters for the conversation.
     * @return Conversation The created streaming conversation instance.
     */
    public function createStreamingConversation(string $model, array $options = []): Conversation
    {
        // Call createConversation correctly, setting streaming flag to true (5th arg)
        return $this->createConversation($model, $options, null, null, true);
    }

    /**
     * Create a new streaming handler instance.
     * Can be overridden if custom handler logic is needed.
     */
    public function createStreamingHandler(?EventHandler $eventHandler = null): StreamingHandler
    {
        $handlerToUse = $eventHandler ?? $this->eventHandler;

        return new StreamingHandler($handlerToUse);
    }

    /**
     * Create the single event handler instance.
     * Called only from the constructor.
     */
    protected function createEventHandler(): EventHandler
    {
        return new EventHandler;
    }

    /**
     * Create a Laravel streaming handler (requires Laravel).
     *
     * @return object|null An instance of LaravelStreamingHandler or null if class doesn't exist.
     *
     * @throws \RuntimeException If the required Laravel class is not available.
     */
    public function createLaravelStreamingHandler(): ?object
    {
        $handlerClass = LARAVEL_STREAMING_HANDLER_CLASS;

        if ($handlerClass === null) {
            throw new \RuntimeException('LaravelStreamingHandler requires Laravel integration classes.');
        }

        return new $handlerClass($this->eventHandler);
    }
}
