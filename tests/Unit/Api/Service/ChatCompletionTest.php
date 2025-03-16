<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;
use Shelfwood\LMStudio\Api\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Api\Service\ChatService;

describe('ChatCompletion', function (): void {
    beforeEach(function (): void {
        $this->apiClient = Mockery::mock(ApiClientInterface::class);
        $this->chatService = new ChatService($this->apiClient);
    });

    test('chat completion returns expected response', function (): void {
        // Load the mock response
        $mockResponse = load_mock('chat/standard-response.json');

        // Create test messages
        $messages = [
            new Message(Role::SYSTEM, 'You are a helpful assistant.'),
            new Message(Role::USER, 'What\'s the weather like in London?'),
        ];

        // Set up the mock to return the mock response
        $this->apiClient->shouldReceive('post')
            ->once()
            ->with('/api/v0/chat/completions', [
                'model' => 'qwen2.5-7b-instruct',
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
        $response = $this->chatService->createCompletion('qwen2.5-7b-instruct', $messages);

        // Assert the response is a ChatCompletionResponse
        expect($response)->toBeInstanceOf(ChatCompletionResponse::class);

        // Assert the response contains the correct data
        expect($response->id)->toBe('chatcmpl-xrxph8k44efncp7wekaa0h');
        expect($response->object)->toBe('chat.completion');
        expect($response->model)->toBe('qwen2.5-7b-instruct');
        expect($response->getChoices())->toHaveCount(1);

        // Assert the content is correct
        expect($response->getContent())->toBe('The capital of France is Paris.');
    });

    test('chat completion with tools returns expected response', function (): void {
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
            'model' => 'qwen2.5-7b-instruct',
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
                return $data['model'] === 'qwen2.5-7b-instruct'
                    && is_array($data['messages'])
                    && is_array($data['tools'])
                    && $data['tools'][0]['type'] === 'function'
                    && $data['tools'][0]['function']['name'] === 'get_weather';
            }))
            ->andReturn($apiResponse);

        // Call the createCompletion method
        $response = $this->chatService->createCompletion('qwen2.5-7b-instruct', $messages, [$definition]);

        // Assert the response is a ChatCompletionResponse
        expect($response)->toBeInstanceOf(ChatCompletionResponse::class);

        // Assert the response contains the correct data
        expect($response->id)->toBe('chatcmpl-123');
        expect($response->object)->toBe('chat.completion');
        expect($response->model)->toBe('qwen2.5-7b-instruct');
        expect($response->getChoices())->toHaveCount(1);

        // Assert the content is correct
        expect($response->getContent())->toBe('I will check the weather in London.');
        expect($response->hasToolCalls())->toBeTrue();
        expect($response->getToolCalls())->toHaveCount(1);
        expect($response->getToolCalls()[0]->getType())->toBe('function');
        expect($response->getToolCalls()[0]->getFunction()->getName())->toBe('get_weather');
        expect($response->getToolCalls()[0]->getFunction()->getArguments())->toBe('{"location":"London"}');
    });
});
