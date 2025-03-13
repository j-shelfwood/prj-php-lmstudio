<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Builder\ConversationBuilder;
use Shelfwood\LMStudio\Core\Conversation\Conversation;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;

describe('ConversationBuilder', function (): void {
    beforeEach(function (): void {
        $this->chatService = Mockery::mock(ChatService::class);
        $this->builder = new ConversationBuilder($this->chatService, 'qwen2.5-7b-instruct-1m');
    });

    test('builder creates conversation with correct configuration', function (): void {
        // Configure the builder
        $conversation = $this->builder
            ->withModel('gpt-4o')
            ->withOptions(['temperature' => 0.7])
            ->build();

        // Assert the conversation has the correct configuration
        expect($conversation)->toBeInstanceOf(Conversation::class);
        expect($conversation->getModel())->toBe('gpt-4o');
        expect($conversation->getOptions())->toHaveKey('temperature');
        expect($conversation->getOptions()['temperature'])->toBe(0.7);
        expect($conversation->getToolRegistry())->toBeInstanceOf(ToolRegistry::class);
        expect($conversation->getEventHandler())->toBeInstanceOf(EventHandler::class);
    });

    test('builder registers tools correctly', function (): void {
        // Define a tool function
        $weatherFunction = function ($args) {
            return ['temperature' => 22, 'condition' => 'sunny'];
        };

        // Configure the builder with a tool
        $conversation = $this->builder
            ->withTool(
                'get_weather',
                $weatherFunction,
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
            ->build();

        // Assert the tool is registered
        $toolRegistry = $conversation->getToolRegistry();
        expect($toolRegistry->hasTool('get_weather'))->toBeTrue();

        // Assert the tool can be executed
        $result = $toolRegistry->executeTool('get_weather', ['location' => 'London']);
        expect($result)->toBe(['temperature' => 22, 'condition' => 'sunny']);

        // Assert the tool is included in the options
        $options = $conversation->getOptions();
        expect($options)->toHaveKey('tools');
        expect($options['tools'])->toBeArray();

        // Check the structure of the tools array
        expect($options['tools'])->toHaveKey('get_weather');
        $tool = $options['tools']['get_weather'];
        expect($tool)->toHaveKey('type');
        expect($tool)->toHaveKey('function');
        expect($tool['type'])->toBe('function');
        expect($tool['function'])->toHaveKey('name');
        expect($tool['function']['name'])->toBe('get_weather');
    });

    test('builder registers event callbacks correctly', function (): void {
        // Track callback executions
        $responseCallbackExecuted = false;
        $errorCallbackExecuted = false;
        $toolCallCallbackExecuted = false;
        $chunkCallbackExecuted = false;

        // Configure the builder with callbacks
        $conversation = $this->builder
            ->onResponse(function ($response) use (&$responseCallbackExecuted): void {
                $responseCallbackExecuted = true;
            })
            ->onError(function ($error) use (&$errorCallbackExecuted): void {
                $errorCallbackExecuted = true;
            })
            ->onToolCall(function ($name, $args, $id) use (&$toolCallCallbackExecuted): void {
                $toolCallCallbackExecuted = true;
            })
            ->onChunk(function ($chunk) use (&$chunkCallbackExecuted): void {
                $chunkCallbackExecuted = true;
            })
            ->build();

        // Get the event handler
        $eventHandler = $conversation->getEventHandler();

        // Trigger events
        $eventHandler->trigger('response', new stdClass);
        $eventHandler->trigger('error', new Exception('Test error'));
        $eventHandler->trigger('tool_call', 'test_tool', [], 'tool_id_123');
        $eventHandler->trigger('chunk', ['choices' => []]);

        // Assert callbacks were executed
        expect($responseCallbackExecuted)->toBeTrue();
        expect($errorCallbackExecuted)->toBeTrue();
        expect($toolCallCallbackExecuted)->toBeTrue();
        expect($chunkCallbackExecuted)->toBeTrue();
    });

    test('builder enables streaming correctly', function (): void {
        // Configure the builder with streaming
        $conversation = $this->builder
            ->withStreaming(true)
            ->build();

        // Assert streaming is enabled
        expect($conversation->isStreaming())->toBeTrue();
        expect($conversation->getOptions())->toHaveKey('stream');
        expect($conversation->getOptions()['stream'])->toBeTrue();
    });

    test('builder creates a complete conversation with all features', function (): void {
        // Define a tool function
        $weatherFunction = function ($args) {
            return ['temperature' => 22, 'condition' => 'sunny'];
        };

        // Configure the builder with all features
        $conversation = $this->builder
            ->withModel('gpt-4o')
            ->withOptions(['temperature' => 0.7])
            ->withTool(
                'get_weather',
                $weatherFunction,
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
            ->withStreaming(true)
            ->onResponse(function ($response): void {
                // Response callback
            })
            ->onError(function ($error): void {
                // Error callback
            })
            ->onToolCall(function ($name, $args, $id): void {
                // Tool call callback
            })
            ->onChunk(function ($chunk): void {
                // Chunk callback
            })
            ->build();

        // Assert the conversation has all the features
        expect($conversation->getModel())->toBe('gpt-4o');
        expect($conversation->getOptions())->toHaveKey('temperature');
        expect($conversation->getOptions()['temperature'])->toBe(0.7);
        expect($conversation->getOptions())->toHaveKey('stream');
        expect($conversation->getOptions()['stream'])->toBeTrue();
        expect($conversation->getOptions())->toHaveKey('tools');
        expect($conversation->isStreaming())->toBeTrue();
        expect($conversation->getToolRegistry()->hasTool('get_weather'))->toBeTrue();
        expect($conversation->getEventHandler()->hasCallbacks('response'))->toBeTrue();
        expect($conversation->getEventHandler()->hasCallbacks('error'))->toBeTrue();
        expect($conversation->getEventHandler()->hasCallbacks('tool_call'))->toBeTrue();
        expect($conversation->getEventHandler()->hasCallbacks('chunk'))->toBeTrue();
    });
});
