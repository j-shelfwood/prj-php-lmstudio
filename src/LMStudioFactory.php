<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shelfwood\LMStudio\Api\Client\ApiClient;
use Shelfwood\LMStudio\Api\Client\HttpClient;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Api\Service\CompletionService;
use Shelfwood\LMStudio\Api\Service\EmbeddingService;
use Shelfwood\LMStudio\Api\Service\ModelService;
use Shelfwood\LMStudio\Core\Builder\ConversationBuilder;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Manager\ConversationManager;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\ToolConfigService;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

// Define constants for Laravel classes if they exist, otherwise null
$laravelQueueableBuilderClass = class_exists('Shelfwood\LMStudio\Laravel\Conversation\QueueableConversationBuilder') ? 'Shelfwood\LMStudio\Laravel\Conversation\QueueableConversationBuilder' : null;
$laravelStreamingHandlerClass = class_exists('Shelfwood\LMStudio\Laravel\Core\Streaming\LaravelStreamingHandler') ? 'Shelfwood\LMStudio\Laravel\Core\Streaming\LaravelStreamingHandler' : null;

if (! defined('LARAVEL_QUEUEABLE_BUILDER_CLASS')) {
    define('LARAVEL_QUEUEABLE_BUILDER_CLASS', $laravelQueueableBuilderClass);
}

if (! defined('LARAVEL_STREAMING_HANDLER_CLASS')) {
    define('LARAVEL_STREAMING_HANDLER_CLASS', $laravelStreamingHandlerClass);
}

class LMStudioFactory
{
    public readonly string $baseUrl;

    /** @var array<string, string> */
    public readonly array $defaultHeaders;

    protected readonly string $apiKey;

    /** @var array<string, mixed>|null */
    private ?array $customToolConfigurations = null;

    // Optional Logger instance
    private LoggerInterface $logger;

    // Single instances of core components
    protected ?HttpClient $httpClient = null;

    protected ?ApiClient $apiClient = null;

    protected ?ModelService $modelService = null;

    protected ?ChatService $chatService = null;

    protected ?CompletionService $completionService = null;

    protected ?EmbeddingService $embeddingService = null;

    public readonly ToolRegistry $toolRegistry;

    public readonly EventHandler $eventHandler;

    // ToolExecutor is no longer readonly here, it's created via factory method
    protected ToolExecutor $toolExecutor;

    public readonly ToolConfigService $toolConfigService;

    // Cache for ExpressionLanguage
    private ?ExpressionLanguage $expressionLanguage = null;

    /**
     * Default tool configurations.
     *
     * @var array<string, array{callback: string, parameters: array<string, mixed>, description: string|null}>
     */
    private static array $defaultToolConfigurations = [
        // Echo tool
        'echo' => [
            'callback' => '__ECHO_CALLBACK__', // Placeholder, actual callable set later
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
            'callback' => '__TIME_CALLBACK__', // Placeholder
            'parameters' => [
                'type' => 'object',
                'properties' => [],
                'required' => [],
            ],
            'description' => 'Get the current server time. Use this whenever asked about the current time.',
        ],
        // Calculator tool - SAFE IMPLEMENTATION
        'calculate' => [
            'callback' => '__CALCULATE_CALLBACK__', // Placeholder
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
            'description' => 'Evaluates a mathematical expression safely.',
        ],
    ];

    /**
     * @param  string  $baseUrl  The base URL of the API
     * @param  array<string, string>  $defaultHeaders  Default headers to include in all requests
     * @param  string  $apiKey  The API key for LMStudio
     * @param  array<string, mixed>  $config  Optional configuration array (e.g., ['default_tools' => [...]])
     * @param  LoggerInterface|null  $logger  Optional PSR-3 Logger
     */
    public function __construct(
        string $baseUrl,
        array $defaultHeaders,
        string $apiKey,
        array $config = [],
        ?LoggerInterface $logger = null
    ) {
        $this->baseUrl = $baseUrl;
        $this->defaultHeaders = $defaultHeaders;
        $this->apiKey = $apiKey;
        $this->customToolConfigurations = $config['default_tools'] ?? null;
        $this->logger = $logger ?? new NullLogger;

        // Initialize core components
        $this->toolRegistry = new ToolRegistry;
        $this->eventHandler = $this->createEventHandler();
        $this->toolExecutor = $this->createToolExecutor();

        // Determine which tool configurations to use
        $toolConfigsToUse = $this->customToolConfigurations ?? self::$defaultToolConfigurations;

        // Inject the actual callbacks
        $this->injectDefaultToolCallbacks($toolConfigsToUse);

        // Create ToolConfigService passing original tool configurations
        $this->toolConfigService = $this->createToolConfigService(
            toolRegistry: $this->toolRegistry,
            toolExecutor: $this->toolExecutor,
            eventHandler: $this->eventHandler,
            toolConfigurations: $toolConfigsToUse // Pass the original configurations
        );
    }

    private function injectDefaultToolCallbacks(array &$toolConfigs): void
    {
        if (isset($toolConfigs['echo']) && $toolConfigs['echo']['callback'] === '__ECHO_CALLBACK__') {
            $toolConfigs['echo']['callback'] = fn (array $args) => $args['message'] ?? 'No message';
        }

        if (isset($toolConfigs['get_current_time']) && $toolConfigs['get_current_time']['callback'] === '__TIME_CALLBACK__') {
            $toolConfigs['get_current_time']['callback'] = fn () => date('Y-m-d H:i:s');
        }

        if (isset($toolConfigs['calculate']) && $toolConfigs['calculate']['callback'] === '__CALCULATE_CALLBACK__') {
            $toolConfigs['calculate']['callback'] = function (array $args) {
                $expression = $args['expression'] ?? '';

                if (empty($expression)) {
                    throw new \InvalidArgumentException('Expression missing.');
                }

                if ($this->expressionLanguage === null) {
                    $this->expressionLanguage = new ExpressionLanguage;
                }

                try {
                    $result = $this->expressionLanguage->evaluate($expression);

                    return is_scalar($result) ? ['result' => $result] : ['error' => 'Invalid result'];
                } catch (\Exception $e) {
                    throw new \InvalidArgumentException('Eval error: '.$e->getMessage());
                }
            };
        }
    }

    /**
     * Get the HTTP client instance. Lazy loaded.
     *
     * @return HttpClient The HTTP client instance
     */
    public function getHttpClient(): HttpClient
    {
        if ($this->httpClient === null) {
            // Corrected: HttpClient constructor now accepts an optional logger
            $this->httpClient = new HttpClient(logger: $this->logger);
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
     *
     * @param  array<string, array{callback: callable, parameters: array<string, mixed>, description: string|null}>  $toolConfigurations  The actual tool configurations to register.
     */
    protected function createToolConfigService(
        ToolRegistry $toolRegistry,
        ToolExecutor $toolExecutor,
        EventHandler $eventHandler, // Kept parameter for consistency
        array $toolConfigurations // Add parameter for configurations
    ): ToolConfigService {
        // Now receives the configurations instead of defining them
        // Correct argument order: Registry, Executor, Config Array, EventHandler (optional)
        return new ToolConfigService(
            toolRegistry: $this->toolRegistry,
            toolExecutor: $this->toolExecutor,
            toolConfigurations: $toolConfigurations,
            eventHandler: $this->eventHandler
        );
    }

    /**
     * Create an API client instance.
     *
     * @internal Used by service creation methods.
     */
    protected function createApiClient(): ApiClient
    {
        return new ApiClient(
            httpClient: $this->getHttpClient(),
            baseUrl: $this->baseUrl,
            defaultHeaders: $this->defaultHeaders
        );
    }

    /**
     * Create a model service instance.
     *
     * @internal Used by getModelService.
     */
    protected function createModelService(): ModelService
    {
        return new ModelService(apiClient: $this->getApiClient());
    }

    /**
     * Create a chat service instance.
     *
     * @internal Used by getChatService.
     */
    protected function createChatService(): ChatService
    {
        return new ChatService(apiClient: $this->getApiClient());
    }

    /**
     * Create a completion service instance.
     *
     * @internal Use getCompletionService() for external access.
     */
    protected function createCompletionService(): CompletionService
    {
        return new CompletionService(apiClient: $this->getApiClient());
    }

    /**
     * Create an embedding service instance.
     *
     * @internal Use getEmbeddingService() for external access.
     */
    protected function createEmbeddingService(): EmbeddingService
    {
        return new EmbeddingService(apiClient: $this->getApiClient());
    }

    /**
     * Create a new conversation builder instance.
     *
     * @param  string  $model  The model ID to use for the conversation.
     * @return ConversationBuilder The created conversation builder instance.
     */
    public function createConversationBuilder(string $model): ConversationBuilder
    {
        $builder = new ConversationBuilder(
            factory: $this,
            model: $model
        );

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
        $builderClass = 'Shelfwood\\LMStudio\\Laravel\\Conversation\\QueueableConversationBuilder';

        if (! class_exists($builderClass)) {
            $this->logger->warning('Attempted to create QueueableConversationBuilder, but the class does not exist. Laravel integration might not be installed.');

            return null;
        }

        // Re-add logic to get queue dispatcher
        $queueDispatcher = null;

        if (function_exists('app') && app()->bound('queue')) {
            $queueDispatcher = app('queue');
        } else {
            $this->logger->error('Laravel queue dispatcher not found or app() helper unavailable. Cannot create functional QueueableConversationBuilder.');

            // It's better to throw or return null if the dispatcher is essential
            throw new \RuntimeException('Laravel queue dispatcher is required for QueueableConversationBuilder but was not found.');
        }

        // Now call the constructor with the defined $queueDispatcher
        return new $builderClass(
            chatService: $this->getChatService(),
            model: $model,
            toolRegistry: $this->toolRegistry,
            eventHandler: $this->eventHandler,
            queueDispatcher: $queueDispatcher,
            queueToolsByDefault: $queueToolsByDefault
        );
    }

    /**
     * Create a new streaming handler instance.
     * Can be overridden if custom handler logic is needed.
     */
    public function createStreamingHandler(?EventHandler $eventHandler = null): StreamingHandler
    {
        $handlerToUse = $eventHandler ?? $this->eventHandler;

        return new StreamingHandler;
    }

    /**
     * Create the single event handler instance.
     * Called only from the constructor.
     */
    protected function createEventHandler(): EventHandler
    {
        return new EventHandler($this->logger);
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
        if (defined('LARAVEL_STREAMING_HANDLER_CLASS') && LARAVEL_STREAMING_HANDLER_CLASS !== null) {
            $handlerClass = LARAVEL_STREAMING_HANDLER_CLASS;

            return new $handlerClass($this->logger);
        }

        return null;
    }

    // Make ToolRegistry easily accessible
    public function getToolRegistry(): ToolRegistry
    {
        return $this->toolRegistry;
    }

    // Make EventHandler easily accessible
    public function getEventHandler(): EventHandler
    {
        return $this->eventHandler;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    // Factory method for ToolExecutor (allows external registry/handler)
    public function createToolExecutor(?ToolRegistry $registry = null, ?EventHandler $eventHandler = null): ToolExecutor
    {
        return new ToolExecutor($registry ?? $this->toolRegistry, $eventHandler ?? $this->eventHandler, $this->logger);
    }

    /**
     * Create a non-streaming conversation manager.
     *
     * This is a convenience method using the ConversationBuilder.
     */
    public function createConversation(
        string $model,
        array $options = [],
        ?ToolRegistry $toolRegistry = null,
        ?EventHandler $eventHandler = null,
    ): ConversationManager {
        $builder = $this->createConversationBuilder($model)
            ->withOptions($options);

        if ($toolRegistry !== null) {
            $builder->withToolRegistry($toolRegistry);
        }

        if ($eventHandler !== null) {
            $builder->withEventHandler($eventHandler);
        }

        return $builder->build();
    }

    /**
     * Create a streaming conversation manager.
     *
     * This is a convenience method using the ConversationBuilder.
     */
    public function createStreamingConversation(string $model, array $options = []): ConversationManager
    {
        // This method should primarily just configure the builder for streaming.
        // Options, toolRegistry, eventHandler should be set via the builder directly.
        return $this->createConversationBuilder($model)
            ->withOptions($options)
            ->withStreaming(true)
            // Removed direct setting of registry/handler here, should be done on builder instance if needed
            ->build();
    }
}
