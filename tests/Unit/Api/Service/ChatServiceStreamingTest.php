<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Exception\ValidationException;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Service\ChatService;

describe('ToolExecutionHandler', function (): void {
beforeEach(function (): void {
    $this->apiClient = Mockery::mock(ApiClientInterface::class);
    $this->chatService = new ChatService($this->apiClient);
});

test('create completion stream with messages', function (): void {
    // Create test messages
    $messages = [
        new Message(Role::SYSTEM, 'You are a helpful assistant.'),
        new Message(Role::USER, 'Hello, how are you?'),
    ];

    // Create a callback
    $callback = function ($chunk): void {
        // Callback function
    };

    // Set up the mock to expect a streaming POST request with the correct data
    $this->apiClient->shouldReceive('postStream')
        ->once()
        ->with('/api/v0/chat/completions', [
            'model' => 'test-model',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant.',
                ],
                [
                    'role' => 'user',
                    'content' => 'Hello, how are you?',
                ],
            ],
            'stream' => true,
        ], $callback);

    // Call the createCompletionStream method
    $this->chatService->createCompletionStream('test-model', $messages, [], $callback);
});

test('create completion stream with tools', function (): void {
    // Create test messages
    $messages = [
        new Message(Role::USER, 'What\'s the weather like in London?'),
    ];

    // Create a tool
    $tool = new Tool(
        ToolType::FUNCTION,
        [
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
        ]
    );

    // Create a callback
    $callback = function ($chunk): void {
        // Callback function
    };

    // Set up the mock to expect a streaming POST request with the correct data
    $this->apiClient->shouldReceive('postStream')
        ->once()
        ->with('/api/v0/chat/completions', [
            'model' => 'test-model',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'What\'s the weather like in London?',
                ],
            ],
            'tools' => [
                [
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
                ],
            ],
            'stream' => true,
        ], $callback);

    // Call the createCompletionStream method
    $this->chatService->createCompletionStream('test-model', $messages, [
        'tools' => [$tool],
    ], $callback);
});

test('create completion stream validates model', function (): void {
    // Create a callback
    $callback = function ($chunk): void {
        // Callback function
    };

    // Expect a ValidationException to be thrown when model is empty
    expect(fn () => $this->chatService->createCompletionStream('', [], [], $callback))
        ->toThrow(ValidationException::class, 'Model is required');
});

test('create completion stream validates messages', function (): void {
    // Create a callback
    $callback = function ($chunk): void {
        // Callback function
    };

    // Expect a ValidationException to be thrown when messages is empty
    expect(fn () => $this->chatService->createCompletionStream('test-model', [], [], $callback))
        ->toThrow(ValidationException::class, 'Messages are required');
});

test('create completion stream validates callback', function (): void {
    // Create test messages
    $messages = [
        new Message(Role::USER, 'Hello, how are you?'),
    ];

    // Expect a ValidationException to be thrown when callback is null
    expect(fn () => $this->chatService->createCompletionStream('test-model', $messages, [], null))
        ->toThrow(ValidationException::class, 'Callback is required for streaming');
});

test('create completion stream ensures stream option is set', function (): void {
    // Create test messages
    $messages = [
        new Message(Role::USER, 'Hello, how are you?'),
    ];

    // Create a callback
    $callback = function ($chunk): void {
        // Callback function
    };

    // Set up the mock to expect a streaming POST request with stream=true
    $this->apiClient->shouldReceive('postStream')
        ->once()
        ->with('/api/v0/chat/completions', Mockery::on(function ($data) {
            return $data['stream'] === true;
        }), $callback);

    // Call the createCompletionStream method with stream=false in options
    $this->chatService->createCompletionStream('test-model', $messages, [
            'stream' => false, // This should be overridden to true
        ], $callback);
    });
});
