<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Laravel\Conversation\QueueableConversationBuilder;
use Shelfwood\LMStudio\Laravel\Tools\QueueableToolExecutionHandler;

describe('QueueableConversationBuilder', function (): void {
    beforeEach(function (): void {
        $this->chatService = Mockery::mock(ChatService::class);
        $this->builder = new QueueableConversationBuilder($this->chatService, 'test-model');
    });

    test('constructor initializes with queueable tool execution handler', function (): void {
        // Use reflection to access protected property
        $reflection = new \ReflectionClass($this->builder);
        $property = $reflection->getProperty('queueableToolExecutionHandler');
        $property->setAccessible(true);

        expect($property->getValue($this->builder))->toBeInstanceOf(QueueableToolExecutionHandler::class);
    });

    test('set tool queueable delegates to handler', function (): void {
        // Create a mock handler
        $mockHandler = Mockery::mock(QueueableToolExecutionHandler::class);

        // Set expectations
        $mockHandler->shouldReceive('setToolQueueable')
            ->once()
            ->with('test-tool', true)
            ->andReturnSelf();

        // Use reflection to replace the handler
        $reflection = new \ReflectionClass($this->builder);
        $property = $reflection->getProperty('queueableToolExecutionHandler');
        $property->setAccessible(true);
        $property->setValue($this->builder, $mockHandler);

        // Call the method
        $result = $this->builder->setToolQueueable('test-tool', true);

        // Assert the result is the builder (for chaining)
        expect($result)->toBe($this->builder);
    });

    test('on tool queued delegates to handler', function (): void {
        // Create a mock handler
        $mockHandler = Mockery::mock(QueueableToolExecutionHandler::class);

        // Create a callback
        $callback = function (): void {};

        // Set expectations
        $mockHandler->shouldReceive('onQueued')
            ->once()
            ->with($callback)
            ->andReturnSelf();

        // Use reflection to replace the handler
        $reflection = new \ReflectionClass($this->builder);
        $property = $reflection->getProperty('queueableToolExecutionHandler');
        $property->setAccessible(true);
        $property->setValue($this->builder, $mockHandler);

        // Call the method
        $result = $this->builder->onToolQueued($callback);

        // Assert the result is the builder (for chaining)
        expect($result)->toBe($this->builder);
    });

    test('with queueable tool registers tool and sets it as queueable', function (): void {
        // Create a mock handler
        $mockHandler = Mockery::mock(QueueableToolExecutionHandler::class);

        // Set expectations
        $mockHandler->shouldReceive('setToolQueueable')
            ->once()
            ->with('get_weather', true)
            ->andReturnSelf();

        // Use reflection to replace the handler
        $reflection = new \ReflectionClass($this->builder);
        $property = $reflection->getProperty('queueableToolExecutionHandler');
        $property->setAccessible(true);
        $property->setValue($this->builder, $mockHandler);

        // Create a tool definition
        $toolDefinition = [
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'description' => 'Get the weather for a location',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => [
                            'type' => 'string',
                            'description' => 'The location to get weather for',
                        ],
                    ],
                    'required' => ['location'],
                ],
            ],
        ];

        // Create a tool executor
        $executor = function ($args) {
            return ['temperature' => 22, 'condition' => 'sunny'];
        };

        // Call the method
        $result = $this->builder->withQueueableTool('get_weather', $executor, ['type' => 'object']);

        // Assert the result is the builder (for chaining)
        expect($result)->toBe($this->builder);
    });
});
