<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Enum\Role;
use Shelfwood\LMStudio\Enum\ToolType;
use Shelfwood\LMStudio\Model\Message;
use Shelfwood\LMStudio\Model\Tool;
use Shelfwood\LMStudio\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Service\ChatService;

beforeEach(function (): void {
    $this->apiClient = Mockery::mock(ApiClientInterface::class);
    $this->chatService = new ChatService($this->apiClient);
});

test('chat completion returns expected response', function (): void {
    // Load the mock response
    $mockResponse = json_decode(file_get_contents(__DIR__.'/../mocks/chat/standard-response.json'), true);

    // Create test messages
    $messages = [
        new Message(Role::SYSTEM, 'You are a helpful assistant.'),
        new Message(Role::USER, 'What\'s the weather like in London?'),
    ];

    // Set up the mock to return the mock response
    $this->apiClient->shouldReceive('post')
        ->once()
        ->with('/api/v0/chat/completions', [
            'model' => 'qwen2.5-7b-instruct-1m',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant.',
                ],
                [
                    'role' => 'user',
                    'content' => 'What\'s the weather like in London?',
                ],
            ],
        ])
        ->andReturn($mockResponse);

    // Call the createCompletion method
    $response = $this->chatService->createCompletion('qwen2.5-7b-instruct-1m', $messages);

    // Assert the response is a ChatCompletionResponse
    expect($response)->toBeInstanceOf(ChatCompletionResponse::class);

    // Assert the response contains the correct data
    expect($response->id)->toBe('chatcmpl-jinpu9j96ag0svrr5z6txi9');
    expect($response->object)->toBe('chat.completion');
    expect($response->model)->toBe('qwen2.5-7b-instruct-1m');
    expect($response->getChoices())->toHaveCount(1);

    // Assert the content is correct
    $expectedContent = "I'm sorry for any inconvenience, but as an AI, I don't have real-time capabilities to provide current weather updates or forecasts. Please check a reliable weather website or app for the most accurate information on the weather in London.";
    expect($response->getContent())->toBe($expectedContent);
});

test('chat completion with tools returns expected response', function (): void {
    // Load the mock response
    $mockResponse = json_decode(file_get_contents(__DIR__.'/../mocks/chat/tool-response.json'), true);

    // Create test messages
    $messages = [
        new Message(Role::USER, 'What\'s the weather like in London?'),
    ];

    // Create a tool
    $tool = new Tool(
        ToolType::FUNCTION,
        [
            'name' => 'get_current_weather',
            'description' => 'Get the current weather in a location',
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

    // Set up the mock to return the mock response
    $this->apiClient->shouldReceive('post')
        ->once()
        ->with('/api/v0/chat/completions', [
            'model' => 'qwen2.5-7b-instruct-1m',
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
                        'name' => 'get_current_weather',
                        'description' => 'Get the current weather in a location',
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
        ->andReturn($mockResponse);

    // Call the createCompletion method
    $response = $this->chatService->createCompletion('qwen2.5-7b-instruct-1m', $messages, [
        'tools' => [$tool->toArray()],
    ]);

    // Assert the response is a ChatCompletionResponse
    expect($response)->toBeInstanceOf(ChatCompletionResponse::class);

    // Assert the response contains the correct data
    expect($response->id)->toBe('chatcmpl-bnxt7kcizqs5l79zojom4q');
    expect($response->object)->toBe('chat.completion');
    expect($response->model)->toBe('qwen2.5-7b-instruct-1m');
    expect($response->getChoices())->toHaveCount(1);

    // Assert the tool calls are correct
    expect($response->hasToolCalls())->toBeTrue();
    expect($response->getToolCalls())->toHaveCount(1);
    expect($response->getToolCalls()[0]['function']['name'])->toBe('get_current_weather');
    expect($response->getToolCalls()[0]['function']['arguments'])->toBe('{"location":"London"}');
});
