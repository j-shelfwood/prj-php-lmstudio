<?php

declare(strict_types=1);

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
use Shelfwood\LMStudio\Core\Tool\ToolConfigService;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\LMStudioFactory;

/** @var LMStudioFactory $factory */
$factory = null; // Define for type hinting

beforeEach(function (): void {
    // Mock only the lowest level dependency if needed (e.g., HttpClient)
    // $this->mockHttpClient = Mockery::mock(HttpClient::class);

    // Instantiate the REAL factory
    // We might need to provide real or minimal mock dependencies here
    // For now, let's assume defaults are okay or mock HttpClient if necessary
    $this->factory = new LMStudioFactory(
        baseUrl: 'http://example.com/api',
        defaultHeaders: [],
        apiKey: 'test-api-key',
        // config: [], // Optional config
        // logger: null // Optional logger
        // If HttpClient is needed: Inject mock or use partial mock for getHttpClient only
    );

    // --- Remove extensive mocking from beforeEach ---
    // Mockery expectations will be added within specific tests IF needed
    // for methods called on services RETURNED by the factory.

});

test('factory provides api client', function (): void {
    $client = $this->factory->getApiClient();
    expect($client)->toBeInstanceOf(ApiClient::class);
    // Check if the factory consistently returns the same instance (singleton behavior)
    expect($this->factory->getApiClient())->toBe($client);
});

test('factory provides model service', function (): void {
    $service = $this->factory->getModelService();
    expect($service)->toBeInstanceOf(ModelService::class);
    expect($this->factory->getModelService())->toBe($service);
});

test('factory provides chat service', function (): void {
    $service = $this->factory->getChatService();
    expect($service)->toBeInstanceOf(ChatService::class);
    expect($this->factory->getChatService())->toBe($service);
});

test('factory provides completion service', function (): void {
    $service = $this->factory->getCompletionService();
    expect($service)->toBeInstanceOf(CompletionService::class);
    expect($this->factory->getCompletionService())->toBe($service);
});

test('factory provides embedding service', function (): void {
    $service = $this->factory->getEmbeddingService();
    expect($service)->toBeInstanceOf(EmbeddingService::class);
    expect($this->factory->getEmbeddingService())->toBe($service);
});

// --- Tests for Core Component Accessors/Creators ---

test('getToolRegistry returns the singleton instance', function (): void {
    $registry1 = $this->factory->getToolRegistry();
    $registry2 = $this->factory->getToolRegistry();
    expect($registry1)->toBeInstanceOf(ToolRegistry::class);
    expect($registry2)->toBe($registry1); // Check for same instance
    // Now we can check the internal property on the real factory
    expect($registry1)->toBe($this->factory->toolRegistry);
});

test('getEventHandler returns the singleton instance', function (): void {
    $handler1 = $this->factory->getEventHandler();
    $handler2 = $this->factory->getEventHandler();
    expect($handler1)->toBeInstanceOf(EventHandler::class);
    expect($handler2)->toBe($handler1);
    // Check internal property
    expect($handler1)->toBe($this->factory->eventHandler);
});

test('createToolExecutor creates an instance', function (): void {
    // Mock dependencies needed by the REAL ToolExecutor constructor if necessary
    // $mockRegistry = Mockery::mock(ToolRegistry::class);
    // $mockHandler = Mockery::mock(EventHandler::class);
    // $executor = $this->factory->createToolExecutor($mockRegistry, $mockHandler);
    $executor = $this->factory->createToolExecutor(); // Call the real method
    expect($executor)->toBeInstanceOf(ToolExecutor::class);
});

// Removed test for non-existent createNonStreamingTurnHandler
// test('createNonStreamingTurnHandler creates an instance', ...);

// Removed test for non-existent createStreamingTurnHandler
// test('createStreamingTurnHandler creates an instance', ...);

test('createStreamingHandler creates an instance', function (): void {
    $handler = $this->factory->createStreamingHandler(); // Call the real method
    expect($handler)->toBeInstanceOf(StreamingHandler::class);
});

test('factory provides singleton tool config service', function (): void {
    // Access the real public readonly property
    $configService1 = $this->factory->toolConfigService;
    $configService2 = $this->factory->toolConfigService;
    expect($configService1)->toBeInstanceOf(ToolConfigService::class);
    expect($configService2)->toBe($configService1);
});

// --- Tests for ConversationManager Creation --- NEW

test('createConversation creates ConversationManager with correct dependencies', function (): void {
    // Mock external calls made by ConversationManager or its dependencies if needed
    // e.g., Mock ChatService calls if createConversation triggers them
    $manager = $this->factory->createConversation('test-model'); // Calls the REAL factory method
    expect($manager)->toBeInstanceOf(ConversationManager::class);
    expect($manager->getModel())->toBe('test-model');
    expect($manager->getOptions())->toBe([]); // Default options
    expect($manager->isStreaming)->toBeFalse();
    // Check the internal state object directly
    expect($manager->state)->toBeInstanceOf(ConversationState::class);
    expect($manager->state->getModel())->toBe('test-model');
    expect($manager->state->getOptions())->toBe([]);
});

test('createConversation with options passes them to state', function (): void {
    $options = ['temperature' => 0.7, 'max_tokens' => 100];
    $manager = $this->factory->createConversation('test-model-options', $options);
    expect($manager)->toBeInstanceOf(ConversationManager::class);
    expect($manager->getModel())->toBe('test-model-options');
    expect($manager->getOptions())->toBe($options);
    expect($manager->isStreaming)->toBeFalse();
    // Check state directly
    expect($manager->state->getOptions())->toBe($options);
});

test('createStreamingConversation creates streaming ConversationManager', function (): void {
    $options = ['temperature' => 0.9];
    $manager = $this->factory->createStreamingConversation('test-model-stream', $options);
    expect($manager)->toBeInstanceOf(ConversationManager::class);
    expect($manager->getModel())->toBe('test-model-stream');
    expect($manager->getOptions())->toBe($options);
    expect($manager->isStreaming)->toBeTrue();
    // Check state directly
    expect($manager->state->getOptions())->toBe($options);
});

// --- Tests for ConversationBuilder Creation ---

test('create conversation builder provides builder instance', function (): void {
    $builder = $this->factory->createConversationBuilder('build-model');
    expect($builder)->toBeInstanceOf(ConversationBuilder::class);
});

// Test createQueueableConversationBuilder (Remains optional, needs Laravel context)
// test('create queueable conversation builder', function() { ... });
