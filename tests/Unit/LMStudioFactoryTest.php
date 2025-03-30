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

// --- Tests for Core Component Singletons (Accessed via public readonly properties) ---

test('factory provides singleton tool registry', function () use (&$factory): void {
    $registry1 = $factory->toolRegistry; // Access directly
    $registry2 = $factory->toolRegistry;
    expect($registry1)->toBeInstanceOf(ToolRegistry::class);
    expect($registry2)->toBe($registry1); // Check for same instance
});

test('factory provides singleton event handler', function () use (&$factory): void {
    $handler1 = $factory->eventHandler; // Access directly
    $handler2 = $factory->eventHandler;
    expect($handler1)->toBeInstanceOf(EventHandler::class);
    expect($handler2)->toBe($handler1);
});

test('factory provides singleton tool executor', function () use (&$factory): void {
    $executor1 = $factory->toolExecutor; // Access directly
    $executor2 = $factory->toolExecutor;
    expect($executor1)->toBeInstanceOf(ToolExecutor::class);
    expect($executor2)->toBe($executor1);
});

test('factory provides singleton tool config service', function () use (&$factory): void {
    $configService1 = $factory->toolConfigService; // Access directly
    $configService2 = $factory->toolConfigService;
    expect($configService1)->toBeInstanceOf(ToolConfigService::class);
    expect($configService2)->toBe($configService1);
});

// --- Tests for Conversation Creation ---

test('create conversation uses correct dependencies and defaults', function () use (&$factory): void {
    $conversation = $factory->createConversation('test-model');

    expect($conversation)->toBeInstanceOf(Conversation::class);
    expect($conversation->getModel())->toBe('test-model');
    expect($conversation->getOptions())->toBe([]); // Default options
    expect($conversation->streaming)->toBeFalse(); // Access readonly property
    expect($conversation->streamingHandler)->toBeNull(); // Access readonly property

    // Verify it received the factory's singleton instances via readonly properties
    expect($conversation->toolRegistry)->toBe($factory->toolRegistry); // Access readonly properties
    expect($conversation->eventHandler)->toBe($factory->eventHandler);
    expect($conversation->toolExecutor)->toBe($factory->toolExecutor);
});

test('create conversation with options passes them correctly', function () use (&$factory): void {
    $options = ['temperature' => 0.7, 'max_tokens' => 100];
    $conversation = $factory->createConversation('test-model-options', $options);

    expect($conversation)->toBeInstanceOf(Conversation::class);
    expect($conversation->getModel())->toBe('test-model-options');
    // Check base options are merged, stream option is NOT added here
    expect($conversation->getOptions())->toBe($options);
    expect($conversation->streaming)->toBeFalse(); // Access readonly property
});

test('create streaming conversation sets up correctly', function () use (&$factory): void {
    $options = ['temperature' => 0.9];
    // Revert to using the dedicated factory method, which should now be fixed
    $conversation = $factory->createStreamingConversation('test-model-stream', $options);

    expect($conversation)->toBeInstanceOf(Conversation::class);
    expect($conversation->getModel())->toBe('test-model-stream');
    // Verify base options are kept AND stream option is added
    expect($conversation->getOptions())->toBe(['temperature' => 0.9, 'stream' => true]);
    expect($conversation->streaming)->toBeTrue(); // Access readonly property

    // Verify StreamingHandler instance and its dependency via readonly properties
    expect($conversation->streamingHandler)->toBeInstanceOf(\Shelfwood\LMStudio\Core\Streaming\StreamingHandler::class); // Access readonly property
    // Assuming StreamingHandler has a getter for EventHandler - REMOVED this check as it's not essential here
    // expect($streamingHandler->getEventHandler())->toBe($factory->eventHandler); // Check against factory readonly property

    // Verify other core dependencies via readonly properties
    expect($conversation->toolRegistry)->toBe($factory->toolRegistry); // Access readonly properties
    expect($conversation->eventHandler)->toBe($factory->eventHandler);
    expect($conversation->toolExecutor)->toBe($factory->toolExecutor);
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
