<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Service;

use Mockery;
use Shelfwood\LMStudio\Api\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Api\Enum\ResponseFormatType;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Exception\ValidationException;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\ResponseFormat;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;
use Shelfwood\LMStudio\Api\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Api\Service\ChatService;

describe('StreamingHandler', function (): void {
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
            Message::forUser('What\'s the weather like in London?'),
        ];

        // Create tool parameters
        $parameters = new ToolParameters;
        $parameters->addProperty('location', new ToolParameter('string', 'The location to get weather for'));
        $parameters->addRequired('location');

        // Create tool definition
        $definition = new ToolDefinition(
            'get_weather',
            'Get the weather for a location',
            $parameters
        );

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
                        'content' => 'I will check the weather in London.',
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
                'prompt_tokens' => 10,
                'completion_tokens' => 10,
                'total_tokens' => 20,
            ],
        ];

        // Set up the mock to expect a POST request with the correct data
        $this->apiClient->shouldReceive('post')
            ->once()
            ->with('/api/v0/chat/completions', Mockery::on(function ($data) {
                return $data['model'] === 'test-model'
                    && is_array($data['messages'])
                    && is_array($data['tools'])
                    && $data['tools'][0]['type'] === 'function'
                    && $data['tools'][0]['function']['name'] === 'get_weather';
            }))
            ->andReturn($apiResponse);

        // Call the createCompletion method
        $response = $this->chatService->createCompletion('test-model', $messages, [$definition]);

        // Assert the response is correct
        expect($response->id)->toBe('chatcmpl-123');
        expect($response->created)->toBe(1677858242);
        expect($response->model)->toBe('test-model');
        expect($response->choices)->toHaveCount(1);
        expect($response->choices[0]->index)->toBe(0);
        expect($response->choices[0]->message['role'])->toBe('assistant');
        expect($response->choices[0]->message['content'])->toBe('I will check the weather in London.');
        expect($response->choices[0]->message['tool_calls'])->toHaveCount(1);
        expect($response->choices[0]->message['tool_calls'][0]['id'])->toBe('call_123');
        expect($response->choices[0]->message['tool_calls'][0]['type'])->toBe('function');
        expect($response->choices[0]->message['tool_calls'][0]['function']['name'])->toBe('get_weather');
        expect($response->choices[0]->message['tool_calls'][0]['function']['arguments'])->toBe('{"location":"London"}');
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

    test('create completion with response format', function (): void {
        // Create test messages
        $messages = [
            Message::forUser('What\'s the weather like in London?'),
        ];

        // Create response format
        $responseFormat = new ResponseFormat(ResponseFormatType::JSON_SCHEMA, [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'The location',
                ],
                'temperature' => [
                    'type' => 'number',
                    'description' => 'The temperature in Celsius',
                ],
            ],
            'required' => ['location', 'temperature'],
        ]);

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
                        'content' => '{"location":"London","temperature":20}',
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
            ->with('/api/v0/chat/completions', Mockery::on(function ($data) {
                return $data['model'] === 'test-model'
                    && is_array($data['messages'])
                    && isset($data['response_format'])
                    && $data['response_format']['type'] === 'json_schema';
            }))
            ->andReturn($apiResponse);

        // Call the createCompletion method
        $response = $this->chatService->createCompletion('test-model', $messages, null, $responseFormat);

        // Assert the response is correct
        expect($response->id)->toBe('chatcmpl-123');
        expect($response->created)->toBe(1677858242);
        expect($response->model)->toBe('test-model');
        expect($response->choices)->toHaveCount(1);
        expect($response->choices[0]->index)->toBe(0);
        expect($response->choices[0]->message['role'])->toBe('assistant');
        expect($response->choices[0]->message['content'])->toBe('{"location":"London","temperature":20}');
    });
});
