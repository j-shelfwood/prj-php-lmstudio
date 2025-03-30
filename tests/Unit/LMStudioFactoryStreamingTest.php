<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Core\Builder\ConversationBuilder;
use Shelfwood\LMStudio\Core\Conversation\Conversation;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\LMStudioFactory;

beforeEach(function (): void {
    $this->factory = new LMStudioFactory('http://example.com/api', [], 'test-api-key');
});

test('create conversation with streaming', function (): void {
    $conversation = $this->factory->createConversation(
        'test-model',
        ['temperature' => 0.7],
        null,
        null,
        true
    );

    expect($conversation)->toBeInstanceOf(Conversation::class);
    expect($conversation->getModel())->toBe('test-model');
    expect($conversation->getOptions())->toHaveKey('temperature');
    expect($conversation->getOptions()['temperature'])->toBe(0.7);
    expect($conversation->getOptions())->toHaveKey('stream');
    expect($conversation->getOptions()['stream'])->toBeTrue();
    expect($conversation->streaming)->toBeTrue();
});

test('create conversation with tool registry', function (): void {
    $toolRegistry = new ToolRegistry;
    $toolRegistry->registerTool(
        'get_weather',
        function ($args) {
            return ['temperature' => 22, 'condition' => 'sunny'];
        },
        [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'The location to get weather for',
                ],
            ],
            'required' => ['location'],
        ],
        'Get the current weather in a location'
    );

    $conversation = $this->factory->createConversation(
        'test-model',
        [],
        $toolRegistry
    );

    expect($conversation)->toBeInstanceOf(Conversation::class);
});

test('create conversation with event handler', function (): void {
    $eventHandler = new EventHandler;
    $eventHandler->on('response', function ($response): void {
        // Response callback
    });

    $conversation = $this->factory->createConversation(
        'test-model',
        [],
        null,
        $eventHandler
    );

    expect($conversation)->toBeInstanceOf(Conversation::class);
    expect($conversation->eventHandler)->toBe($eventHandler);
    expect($conversation->eventHandler->hasCallbacks('response'))->toBeTrue();
});

test('create conversation with all features', function (): void {
    $toolRegistry = new ToolRegistry;
    $toolRegistry->registerTool(
        'get_weather',
        function ($args) {
            return ['temperature' => 22, 'condition' => 'sunny'];
        },
        [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'The location to get weather for',
                ],
            ],
            'required' => ['location'],
        ],
        'Get the current weather in a location'
    );

    $eventHandler = new EventHandler;
    $eventHandler->on('response', function ($response): void {
        // Response callback
    });

    $conversation = $this->factory->createConversation(
        'test-model',
        ['temperature' => 0.7],
        $toolRegistry,
        $eventHandler,
        true
    );

    expect($conversation)->toBeInstanceOf(Conversation::class);
    expect($conversation->getModel())->toBe('test-model');
    expect($conversation->getOptions())->toHaveKey('temperature');
    expect($conversation->getOptions()['temperature'])->toBe(0.7);
    expect($conversation->getOptions())->toHaveKey('stream');
    expect($conversation->getOptions()['stream'])->toBeTrue();
    expect($conversation->streaming)->toBeTrue();
    expect($conversation->eventHandler)->toBe($eventHandler);
});

test('create conversation builder', function (): void {
    $builder = $this->factory->createConversationBuilder('test-model');

    expect($builder)->toBeInstanceOf(ConversationBuilder::class);

    // Configure the builder
    $conversation = $builder
        ->withModel('gpt-4o')
        ->withOptions(['temperature' => 0.7])
        ->withStreaming(true)
        ->withTool(
            'get_weather',
            function ($args) {
                return ['temperature' => 22, 'condition' => 'sunny'];
            },
            [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The location to get weather for',
                    ],
                ],
                'required' => ['location'],
            ],
            'Get the current weather in a location'
        )
        ->onResponse(function ($response): void {
            // Response callback
        })
        ->build();

    // Assert the conversation has the correct configuration
    expect($conversation)->toBeInstanceOf(Conversation::class);
    expect($conversation->getModel())->toBe('gpt-4o');
    expect($conversation->getOptions())->toHaveKey('temperature');
    expect($conversation->getOptions()['temperature'])->toBe(0.7);
    expect($conversation->getOptions())->toHaveKey('stream');
    expect($conversation->getOptions()['stream'])->toBeTrue();
    expect($conversation->streaming)->toBeTrue();
    expect($conversation->eventHandler->hasCallbacks('response'))->toBeTrue();
});
