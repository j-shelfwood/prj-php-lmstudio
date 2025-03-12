<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Enum\Role;
use Shelfwood\LMStudio\Enum\ToolType;
use Shelfwood\LMStudio\Exception\ValidationException;
use Shelfwood\LMStudio\Model\Message;
use Shelfwood\LMStudio\Model\Tool;
use Shelfwood\LMStudio\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Service\ChatService;

beforeEach(function (): void {
    $this->apiClient = Mockery::mock(ApiClientInterface::class);
    $this->chatService = new ChatService($this->apiClient);
});

test('create completion with messages', function (): void {
    // Create test messages
    $messages = [
        new Message(Role::SYSTEM, 'You are a helpful assistant.'),
        new Message(Role::USER, 'Hello, how are you?'),
    ];

    // Mock the API response
    $apiResponse = [
        'id' => 'chatcmpl-123',
        'object' => 'chat.completion',
        'created' => 1677858242,
        'model' => 'test-model',
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'I am doing well, thank you for asking!',
                ],
                'finish_reason' => 'stop',
            ],
        ],
        'usage' => [
            'prompt_tokens' => 10,
            'completion_tokens' => 10,
            'total_tokens' => 20,
        ],
    ];

    // Set up the mock to expect a POST request with the correct data
    $this->apiClient->shouldReceive('post')
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
        ])
        ->andReturn($apiResponse);

    // Call the createCompletion method
    $response = $this->chatService->createCompletion('test-model', $messages);

    // Assert the response is a ChatCompletionResponse
    expect($response)->toBeInstanceOf(ChatCompletionResponse::class);

    // Assert the response contains the correct data
    expect($response->id)->toBe('chatcmpl-123');
    expect($response->object)->toBe('chat.completion');
    expect($response->model)->toBe('test-model');
    expect($response->getChoices())->toHaveCount(1);
    expect($response->getContent())->toBe('I am doing well, thank you for asking!');
});

test('create completion with tools', function (): void {
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

    // Mock the API response
    $apiResponse = [
        'id' => 'chatcmpl-456',
        'object' => 'chat.completion',
        'created' => 1677858242,
        'model' => 'test-model',
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'tool_calls' => [
                        [
                            'id' => 'call_123',
                            'type' => 'function',
                            'function' => [
                                'name' => 'get_weather',
                                'arguments' => '{"location":"London"}',
                            ],
                        ],
                    ],
                ],
                'finish_reason' => 'tool_calls',
            ],
        ],
        'usage' => [
            'prompt_tokens' => 15,
            'completion_tokens' => 15,
            'total_tokens' => 30,
        ],
    ];

    // Set up the mock to expect a POST request with the correct data
    $this->apiClient->shouldReceive('post')
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
        ])
        ->andReturn($apiResponse);

    // Call the createCompletion method
    $response = $this->chatService->createCompletion('test-model', $messages, [
        'tools' => [$tool->toArray()],
    ]);

    // Assert the response is a ChatCompletionResponse
    expect($response)->toBeInstanceOf(ChatCompletionResponse::class);

    // Assert the response contains the correct data
    expect($response->id)->toBe('chatcmpl-456');
    expect($response->object)->toBe('chat.completion');
    expect($response->model)->toBe('test-model');
    expect($response->getChoices())->toHaveCount(1);
    expect($response->hasToolCalls())->toBeTrue();
    expect($response->getToolCalls())->toHaveCount(1);
    expect($response->getToolCalls()[0]['function']['name'])->toBe('get_weather');
});

test('create completion validates model', function (): void {
    // Expect a ValidationException to be thrown when model is empty
    expect(fn () => $this->chatService->createCompletion('', []))
        ->toThrow(ValidationException::class, 'Model is required');
});

test('create completion validates messages', function (): void {
    // Expect a ValidationException to be thrown when messages is empty
    expect(fn () => $this->chatService->createCompletion('test-model', []))
        ->toThrow(ValidationException::class, 'Messages are required');
});
