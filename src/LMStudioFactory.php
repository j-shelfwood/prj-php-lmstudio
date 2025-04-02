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
use Shelfwood\LMStudio\Core\Conversation\ConversationState;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Manager\ConversationManager;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\Exception\ToolExecutionException;
use Shelfwood\LMStudio\Core\Tool\Exception\ToolInvalidInputException;
use Shelfwood\LMStudio\Core\Tool\ToolConfigService;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\Core\Turn\NonStreamingTurnHandler;
use Shelfwood\LMStudio\Core\Turn\StreamingTurnHandler;
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
     * @param  array<string, mixed>|null  $customToolConfigurations
     */
    public function __construct(
        string $baseUrl,
        array $defaultHeaders,
        string $apiKey,
        ?LoggerInterface $logger = null,
        ?array $customToolConfigurations = null
    ) {
        $this->baseUrl = $baseUrl;
        $this->defaultHeaders = $defaultHeaders;
        $this->apiKey = $apiKey;
        $this->logger = $logger ?? new NullLogger;

        $this->customToolConfigurations = $customToolConfigurations ?? self::$defaultToolConfigurations;

        // Initialize core components
        $this->eventHandler = $this->createEventHandler();
        $this->toolRegistry = new ToolRegistry;
        $this->toolExecutor = $this->createToolExecutor();

        // Determine which tool configurations to use
        $toolConfigsToUse = $this->customToolConfigurations;

        // Inject the actual callbacks
        $this->injectDefaultToolCallbacks($toolConfigsToUse);

        // Create ToolConfigService passing original tool configurations
        $this->toolConfigService = $this->createToolConfigService(
            toolRegistry: $this->toolRegistry,
            toolExecutor: $this->toolExecutor,
            eventHandler: $this->eventHandler,
            toolConfigurations: $toolConfigsToUse
        );
    }

    private function injectDefaultToolCallbacks(array &$toolConfigs): void
    {
        if (isset($toolConfigs['echo']) && $toolConfigs['echo']['callback'] === '__ECHO_CALLBACK__') {
            $toolConfigs['echo']['callback'] = function (array $args) {
                if (! isset($args['message']) || ! is_string($args['message'])) {
                    // Use Shelfwood exception
                    throw new ToolInvalidInputException('Missing or invalid "message" argument for echo tool.', ['arguments' => $args]);
                }

                return $args['message']; // Return directly, executor will handle encoding
            };
        }

        if (isset($toolConfigs['get_current_time']) && $toolConfigs['get_current_time']['callback'] === '__TIME_CALLBACK__') {
            // Return directly, executor will handle encoding
            $toolConfigs['get_current_time']['callback'] = fn () => ['time' => date('Y-m-d H:i:s')];
        }

        if (isset($toolConfigs['calculate']) && $toolConfigs['calculate']['callback'] === '__CALCULATE_CALLBACK__') {
            $toolConfigs['calculate']['callback'] = function (array $args) {
                $expression = $args['expression'] ?? null;

                if (empty($expression) || ! is_string($expression)) {
                    // Use Shelfwood exception
                    throw new ToolInvalidInputException('Missing or invalid "expression" argument for calculate tool.', ['arguments' => $args]);
                }

                if ($this->expressionLanguage === null) {
                    $this->expressionLanguage = new ExpressionLanguage;
                }

                try {
                    $result = $this->expressionLanguage->evaluate($expression);

                    if (! is_scalar($result)) {
                        // Use Shelfwood exception
                        throw new ToolExecutionException('Calculation resulted in a non-scalar value.', ['expression' => $expression, 'result_type' => gettype($result)]);
                    }

                    // Return structured result, executor will encode
                    return ['result' => $result];
                } catch (\Exception $e) {
                    // Wrap calculation errors in Shelfwood exception
                    throw new ToolExecutionException('Error evaluating expression: '.$e->getMessage(), ['expression' => $expression], 0, $e);
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
    private function createToolConfigService(
        ToolRegistry $toolRegistry,
        ToolExecutor $toolExecutor,
        EventHandler $eventHandler,
        array $toolConfigurations
    ): ToolConfigService {
        // Correct 4 arguments, EventHandler is LAST and optional
        return new ToolConfigService(
            $toolRegistry,
            $toolExecutor,
            $toolConfigurations, // Config array is 3rd
            $eventHandler // EventHandler is 4th
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
    public function createConversationBuilder(string $model, array $options = []): ConversationBuilder
    {
        // Correct 2 arguments for constructor, add withOptions call
        $builder = new ConversationBuilder(
            factory: $this,
            model: $model
        );
        $builder->withOptions($options);

        return $builder;
    }

    /**
     * Create a new streaming handler instance.
     * Can be overridden if custom handler logic is needed.
     */
    public function createStreamingHandler(): StreamingHandler
    {
        return new StreamingHandler($this->getLogger());
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
    public function createToolExecutor(): ToolExecutor
    {
        $eventHandler = $this->getEventHandler();
        $registry = $this->getToolRegistry();
        $logger = $this->getLogger();

        return new ToolExecutor($registry, $eventHandler, $logger);
    }

    /**
     * Create a non-streaming conversation manager.
     *
     * This is a convenience method using the ConversationBuilder.
     */
    public function createConversation(string $model, array $options = []): ConversationManager
    {
        $state = new ConversationState($model, $options);
        $eventHandler = $this->getEventHandler();
        $streamProcessor = null; // No stream processor for non-streaming
        $isStreaming = false;

        // Create Manager using new constructor (4 args: state, eventHandler, streamProcessor, isStreaming)
        $manager = new ConversationManager(
            $state,
            $eventHandler,
            $streamProcessor,
            $isStreaming
        );

        // Create the handler, passing the manager
        $turnHandler = $this->createNonStreamingTurnHandler($manager);

        // Set the handler on the manager
        $manager->setNonStreamingTurnHandler($turnHandler);

        return $manager;
    }

    /**
     * Create a streaming conversation manager.
     *
     * This is a convenience method using the ConversationBuilder.
     */
    public function createStreamingConversation(string $model, array $options = []): ConversationManager
    {
        $state = new ConversationState($model, $options);
        $eventHandler = $this->getEventHandler();
        $streamProcessor = $this->createStreamingHandler(); // Create stream processor for streaming
        $isStreaming = true;

        // Create Manager using new constructor (4 args)
        $manager = new ConversationManager(
            $state,
            $eventHandler,
            $streamProcessor,
            $isStreaming
        );

        // Create the handler, passing the manager
        $turnHandler = $this->createStreamingTurnHandler($manager);

        // Set the handler on the manager
        $manager->setStreamingTurnHandler($turnHandler);

        return $manager;
    }

    public function createNonStreamingTurnHandler(ConversationManager $manager): NonStreamingTurnHandler
    {
        // Correct 5 arguments: ChatService, ToolRegistry, ToolExecutor, EventHandler, Logger
        return new NonStreamingTurnHandler(
            $this->getChatService(),
            $this->getToolRegistry(),
            $this->createToolExecutor(), // Creates one with Registry, Handler, Logger
            $this->getEventHandler(),
            $this->getLogger()
        );
    }

    public function createStreamingTurnHandler(ConversationManager $manager): StreamingTurnHandler
    {
        // Correct 6 arguments: ChatService, ToolRegistry, ToolExecutor, EventHandler, StreamingHandler, Logger
        return new StreamingTurnHandler(
            $this->getChatService(),
            $this->getToolRegistry(),
            $this->createToolExecutor(), // Creates one with Registry, Handler, Logger
            $this->getEventHandler(),
            $this->createStreamingHandler(), // Creates one with Logger
            $this->getLogger()
        );
    }
}
