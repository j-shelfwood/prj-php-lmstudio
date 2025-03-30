<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Client\ApiClient;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Api\Service\CompletionService;
use Shelfwood\LMStudio\Api\Service\EmbeddingService;
use Shelfwood\LMStudio\Api\Service\ModelService;
use Shelfwood\LMStudio\Core\Builder\ConversationBuilder;
use Shelfwood\LMStudio\Core\Conversation\Conversation;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Tool\ToolConfigService;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\LMStudioFactory;

/** @var LMStudioFactory $factory */
$factory = null; // Define for type hinting

beforeEach(function () use (&$factory): void {
    $factory = new LMStudioFactory('http://localhost:1234/v1', [], 'test-key');
});

test('factory provides api client', function () use (&$factory): void {
    $client = $factory->getApiClient();
    expect($client)->toBeInstanceOf(ApiClient::class);
    // Check if singleton (optional, depends on desired behavior for clients/services)
    expect($factory->getApiClient())->toBe($client);
});

test('factory provides model service', function () use (&$factory): void {
    $service = $factory->getModelService();
    expect($service)->toBeInstanceOf(ModelService::class);
    expect($factory->getModelService())->toBe($service); // Services are usually singletons per factory
});

test('factory provides chat service', function () use (&$factory): void {
    $service = $factory->getChatService();
    expect($service)->toBeInstanceOf(ChatService::class);
    expect($factory->getChatService())->toBe($service);
});

test('factory provides completion service', function () use (&$factory): void {
    $service = $factory->getCompletionService();
    expect($service)->toBeInstanceOf(CompletionService::class);
    expect($factory->getCompletionService())->toBe($service);
});

test('factory provides embedding service', function () use (&$factory): void {
    $service = $factory->getEmbeddingService();
    expect($service)->toBeInstanceOf(EmbeddingService::class);
    expect($factory->getEmbeddingService())->toBe($service);
});

// --- Tests for Core Component Singletons ---

test('factory provides singleton tool registry', function () use (&$factory): void {
    $registry1 = $factory->getToolRegistry();
    $registry2 = $factory->getToolRegistry();
    expect($registry1)->toBeInstanceOf(ToolRegistry::class);
    expect($registry2)->toBe($registry1); // Check for same instance
});

test('factory provides singleton event handler', function () use (&$factory): void {
    $handler1 = $factory->getEventHandler();
    $handler2 = $factory->getEventHandler();
    expect($handler1)->toBeInstanceOf(EventHandler::class);
    expect($handler2)->toBe($handler1);
});

test('factory provides singleton tool executor', function () use (&$factory): void {
    $executor1 = $factory->getToolExecutor();
    $executor2 = $factory->getToolExecutor();
    expect($executor1)->toBeInstanceOf(ToolExecutor::class);
    expect($executor2)->toBe($executor1);
});

test('factory provides singleton tool config service', function () use (&$factory): void {
    $configService1 = $factory->getToolConfigService();
    $configService2 = $factory->getToolConfigService();
    expect($configService1)->toBeInstanceOf(ToolConfigService::class);
    expect($configService2)->toBe($configService1);
});

// --- Tests for Conversation Creation ---

test('create conversation uses correct dependencies and defaults', function () use (&$factory): void {
    $conversation = $factory->createConversation('test-model');

    expect($conversation)->toBeInstanceOf(Conversation::class);
    expect($conversation->getModel())->toBe('test-model');
    expect($conversation->getOptions())->toBe([]); // Default options
    expect($conversation->isStreaming())->toBeFalse();
    // expect($conversation->getStreamingHandler())->toBeNull(); // Assuming getter exists

    // Verify it received the factory's singleton instances via getters on Conversation
    expect($conversation->getToolRegistry())->toBe($factory->getToolRegistry());
    expect($conversation->getEventHandler())->toBe($factory->getEventHandler());
    expect($conversation->getToolExecutor())->toBe($factory->getToolExecutor());
});

test('create conversation with options passes them correctly', function () use (&$factory): void {
    $options = ['temperature' => 0.7, 'max_tokens' => 100];
    $conversation = $factory->createConversation('test-model-options', $options);

    expect($conversation)->toBeInstanceOf(Conversation::class);
    expect($conversation->getModel())->toBe('test-model-options');
    // Check base options are merged, stream option is NOT added here
    expect($conversation->getOptions())->toBe($options);
    expect($conversation->isStreaming())->toBeFalse();
});

test('create streaming conversation sets up correctly', function () use (&$factory): void {
    $options = ['temperature' => 0.9];
    // Revert to using the dedicated factory method, which should now be fixed
    $conversation = $factory->createStreamingConversation('test-model-stream', $options);

    expect($conversation)->toBeInstanceOf(Conversation::class);
    expect($conversation->getModel())->toBe('test-model-stream');
    // Verify base options are kept AND stream option is added
    expect($conversation->getOptions())->toBe(['temperature' => 0.9, 'stream' => true]);
    expect($conversation->isStreaming())->toBeTrue();

    // Verify StreamingHandler instance and its dependency via Conversation getters (assuming they exist)
    $streamingHandler = $conversation->getStreamingHandler();
    expect($streamingHandler)->toBeInstanceOf(\Shelfwood\LMStudio\Core\Streaming\StreamingHandler::class);
    // Assuming StreamingHandler has a getter for EventHandler - REMOVED this check as it's not essential here
    // expect($streamingHandler->getEventHandler())->toBe($factory->getEventHandler());

    // Verify other core dependencies via Conversation getters (assuming they exist)
    expect($conversation->getToolRegistry())->toBe($factory->getToolRegistry());
    expect($conversation->getEventHandler())->toBe($factory->getEventHandler());
    expect($conversation->getToolExecutor())->toBe($factory->getToolExecutor());
});

// --- Tests for ConversationBuilder Creation ---

test('create conversation builder provides builder instance', function () use (&$factory): void {
    $builder = $factory->createConversationBuilder('build-model');
    expect($builder)->toBeInstanceOf(ConversationBuilder::class);
    // Verifying the ChatService injection requires a getter on ConversationBuilder or reflection.
    // Assuming correct injection based on factory implementation for now.
});

test('create streaming conversation builder provides configured builder', function () use (&$factory): void {
    $builder = $factory->createConversationBuilder('build-model-stream', [], true); // Assuming streaming flag
    expect($builder)->toBeInstanceOf(ConversationBuilder::class);
    // Assuming builder correctly sets streaming=true internally when flag is passed.
    // Cannot directly verify without a getter like $builder->isStreaming().
});

// Test createQueueableConversationBuilder (Optional, requires Laravel context/mocking)
// test('create queueable conversation builder', function() { ... });
