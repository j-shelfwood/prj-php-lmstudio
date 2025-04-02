<?php

declare(strict_types=1);

// REMOVE Composer autoloader require
// require_once __DIR__ . '/../../vendor/autoload.php';

use Mockery; // Use the base TestCase
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Conversation\ConversationState;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Manager\ConversationManager;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\LMStudioFactory;
use Tests\TestCase;

/** @var LMStudioFactory $factory */
$factory = null;

beforeEach(function (): void {
    // Mock only the lowest level dependency if needed
    // $this->mockHttpClient = Mockery::mock(HttpClient::class);

    // Instantiate the REAL factory
    $this->factory = new LMStudioFactory(
        baseUrl: 'http://example.com/api',
        defaultHeaders: [],
        apiKey: 'test-api-key'
    );

    // Mock dependencies needed by specific tests IF they make external calls
    // e.g., Mock ChatService for tests involving conversation execution
    $this->mockChatService = Mockery::mock(ChatService::class);
    // Inject this mock if needed, perhaps using a partial mock just for getChatService?
    // Or, modify tests to not rely on actual ChatService execution.
});

test('create streaming conversation creates manager correctly', function (): void {
    $manager = $this->factory->createStreamingConversation('test-model');
    expect($manager)->toBeInstanceOf(ConversationManager::class);
    expect($manager->isStreaming)->toBeTrue();
    expect($manager->state)->toBeInstanceOf(ConversationState::class);
});

test('create conversation with tool registry uses builder', function (): void {
    $toolRegistry = new ToolRegistry;
    $toolRegistry->registerTool(
        'custom_tool',
        fn () => 'result',
        ['type' => 'object', 'properties' => []]
    );

    // Use the real builder from the real factory
    $manager = $this->factory->createConversationBuilder('test-model')
        ->withToolRegistry($toolRegistry)
        ->build();

    expect($manager)->toBeInstanceOf(ConversationManager::class);
    // Verify the correct ToolRegistry was used (requires getter or reflection on manager/turn handler)
    // For now, trust the builder wired it correctly.
});

test('create conversation with event handler uses builder', function (): void {
    $eventHandler = new EventHandler;
    $eventHandler->on('test.event', function (): void {
        // Custom event logic
    });

    // Use the real builder
    $manager = $this->factory->createConversationBuilder('test-model')
        ->withEventHandler($eventHandler)
        ->build();

    expect($manager)->toBeInstanceOf(ConversationManager::class);
    // Verify the correct EventHandler was used (requires getter or reflection)
});

test('create conversation builder builds manager with features', function (): void {
    // Get the real builder
    $builder = $this->factory->createConversationBuilder('initial-model');

    // Variables for callbacks
    $toolExecuted = false;
    $streamContent = '';

    // Configure the real builder
    $manager = $builder
        ->withModel('gpt-4o')
        ->withOptions(['temperature' => 0.7])
        ->withStreaming(true)
        ->withTool(
            'get_weather',
            function (array $args): array {
                $toolExecuted = true; // Mark tool as executed

                return ['temperature' => '25C', 'condition' => $args['location'] === 'tokyo' ? 'sunny' : 'cloudy'];
            },
            [
                'type' => 'object',
                'properties' => [
                    'location' => ['type' => 'string', 'description' => 'City name'],
                ],
                'required' => ['location'],
            ]
            // null description implied
        )
        ->onStreamContent(function (string $chunk) use (&$streamContent): void { // ...
            $streamContent .= $chunk;
        })
        ->build();

    // Assertions on the final result (the real manager)
    expect($manager)->toBeInstanceOf(ConversationManager::class);
    expect($manager->getModel())->toBe('gpt-4o');
    expect($manager->getOptions())->toBe(['temperature' => 0.7]);
    expect($manager->isStreaming)->toBeTrue();

    // We could potentially execute a turn here and verify the tool/stream callbacks fired,
    // but that might require mocking the ChatService call within the turn handler.
    // For now, we test that the builder successfully builds the manager with the config.
});
