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
use Shelfwood\LMStudio\Core\Conversation\Conversation;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\ToolConfigService;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

// Define constants for Laravel classes if they exist, otherwise null
// This avoids hard errors if Laravel integration isn't used/installed.
/* REMOVED define() blocks
if (! defined('LARAVEL_QUEUEABLE_BUILDER_CLASS')) {
    define('LARAVEL_QUEUEABLE_BUILDER_CLASS', class_exists('Shelfwood\\LMStudio\\Laravel\\Conversation\\QueueableConversationBuilder') ? 'Shelfwood\\LMStudio\\Laravel\\Conversation\\QueueableConversationBuilder' : null);
}

if (! defined('LARAVEL_STREAMING_HANDLER_CLASS')) {
    define('LARAVEL_STREAMING_HANDLER_CLASS', class_exists('Shelfwood\\LMStudio\\Laravel\\Core\\Streaming\\LaravelStreamingHandler') ? 'Shelfwood\\LMStudio\\Laravel\\Core\\Streaming\\LaravelStreamingHandler' : null);
}
*/

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

    public readonly ToolExecutor $toolExecutor;

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
        array $config = [], // Add config array
        ?LoggerInterface $logger = null // Add logger parameter
    ) {
        $this->baseUrl = $baseUrl;
        $this->defaultHeaders = $defaultHeaders;
        $this->apiKey = $apiKey;
        $this->customToolConfigurations = $config['default_tools'] ?? null; // Store custom tool config
        $this->logger = $logger ?? new NullLogger; // Store the logger

        // Initialize core components ONCE using public readonly properties
        $this->toolRegistry = new ToolRegistry;
        $this->eventHandler = $this->createEventHandler();
        $this->toolExecutor = new ToolExecutor(
            registry: $this->toolRegistry,
            eventHandler: $this->eventHandler
        );

        // Determine which tool configurations to use
        $toolConfigsToUse = $this->customToolConfigurations ?? self::$defaultToolConfigurations;

        // Need to inject the actual callbacks here because they depend on the factory instance ($this)
        if (isset($toolConfigsToUse['echo']) && $toolConfigsToUse['echo']['callback'] === '__ECHO_CALLBACK__') {
            $toolConfigsToUse['echo']['callback'] = function (array $args) {
                return $args['message'] ?? 'No message provided';
            };
        }

        if (isset($toolConfigsToUse['get_current_time']) && $toolConfigsToUse['get_current_time']['callback'] === '__TIME_CALLBACK__') {
            $toolConfigsToUse['get_current_time']['callback'] = function () {
                return date('Y-m-d H:i:s');
            };
        }

        if (isset($toolConfigsToUse['calculate']) && $toolConfigsToUse['calculate']['callback'] === '__CALCULATE_CALLBACK__') {
            $toolConfigsToUse['calculate']['callback'] = function (array $args) {
                $expression = $args['expression'] ?? '';

                if (empty($expression)) {
                    throw new \InvalidArgumentException('Mathematical expression cannot be empty.');
                }

                if ($this->expressionLanguage === null) {
                    $this->expressionLanguage = new ExpressionLanguage(null, []);
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
            };
        }

        $this->toolConfigService = $this->createToolConfigService(
            toolRegistry: $this->toolRegistry,
            toolExecutor: $this->toolExecutor,
            eventHandler: $this->eventHandler,
            toolConfigurations: $toolConfigsToUse // Pass the resolved configurations
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
     * Create a new conversation instance.
     *
     * @param  string  $model  The model ID to use for the conversation.
     * @param  array<string, mixed>  $options  Additional options for the conversation (e.g., temperature).
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
            chatService: $this->getChatService(), // Pass the ChatService instance
            model: $model,
            options: $finalOptions, // Use the potentially modified options
            toolRegistry: $registry,
            eventHandler: $handler,
            streaming: $streaming, // Pass the boolean flag
            streamingHandler: $streamingHandler, // Pass the created handler or null
            toolExecutor: $executor
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
        $builder = new ConversationBuilder(
            chatService: $this->getChatService(),
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
     * Create a streaming conversation instance.
     *
     * @param  string  $model  The model to use for the conversation.
     * @param  array<string, mixed>  $options  Optional parameters for the conversation.
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

        return new StreamingHandler;
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
        // Check if Laravel context is needed and the class exists
        // Use fully qualified class name directly
        $handlerClass = 'Shelfwood\\LMStudio\\Laravel\\Core\\Streaming\\LaravelStreamingHandler';

        if (! class_exists($handlerClass)) {
            throw new \RuntimeException('LaravelStreamingHandler requires the Laravel integration package (shelfwood/lmstudio-php-laravel).');
        }

        return new $handlerClass($this->eventHandler);
    }
}
