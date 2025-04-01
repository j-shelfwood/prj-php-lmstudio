<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Service;

// Keep PHPUnit TestCase for potential base setup, but tests will be functional
// use PHPUnit\Framework\TestCase;

// Remove unused:
// use Mockery;

// Project & Vendor Uses
use Shelfwood\LMStudio\Api\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Api\Enum\FinishReason;
use Shelfwood\LMStudio\Api\Enum\ResponseFormatType;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Exception\ValidationException;
use Shelfwood\LMStudio\Api\Model\Choice;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\ResponseFormat;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;
use Shelfwood\LMStudio\Api\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Api\Service\ChatService;

// Use global Mockery

beforeEach(function (): void {
    // Use $this context provided by Pest
    $this->apiClient = \Mockery::mock(ApiClientInterface::class);
    $this->chatService = new ChatService($this->apiClient);
});

// Convert class methods to test() functions
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
            // Add other potential default options if ChatService adds them
            // 'temperature' => 1.0,
            // 'stream' => false,
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
}); // Ensure closing parenthesis matches test()

test('create completion with tools', function (): void { // Use closure arrow fn `() =>` or regular `function()`
    // Create test messages
    $messages = [
        Message::forUser('What\'s the weather like in London?'),
    ];

    // Create tool parameters
    $parameters = new ToolParameters;
    $parameters->addProperty('location', new ToolParameter('string', 'The location to get weather for'));
    $parameters->addRequired('location');

    // Create tool definition and tool object
    $toolDefinition = new ToolDefinition('get_weather', 'Get the weather for a location', $parameters);
    $tool = new Tool(ToolType::FUNCTION, $toolDefinition);

    // Expected arrays based on models
    $expectedMessagesArray = array_map(fn (Message $m) => $m->toArray(), $messages);
    $expectedToolsArray = [$tool->toArray()];

    // Mock the API response (ensure it matches ChatCompletionResponse structure)
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
                    'content' => 'I will check the weather in London.', // Can be null if only tool_calls present
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
                'logprobs' => null, // Add potentially missing fields for strictness
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
        ->with('/api/v0/chat/completions', \Mockery::on(function ($data) use ($expectedMessagesArray, $expectedToolsArray) {
            // Basic checks
            if (($data['model'] ?? null) !== 'test-model') {
                return false;
            }

            if (($data['messages'] ?? []) !== $expectedMessagesArray) {
                return false;
            }

            if (! isset($data['tools']) || ! is_array($data['tools'])) {
                return false;
            }

            if (count($data['tools']) !== count($expectedToolsArray)) {
                return false;
            }

            // More robust comparison might be needed depending on object structure after toArray()
            return json_encode($data['tools']) === json_encode($expectedToolsArray);
        }))
        ->andReturn($apiResponse);

    // Call the createCompletion method
    $response = $this->chatService->createCompletion('test-model', $messages, [$tool]);

    // Assert the response is correct
    expect($response)->toBeInstanceOf(ChatCompletionResponse::class);
    expect($response->id)->toBe('chatcmpl-123');
    // ... other basic assertions ...
    expect($response->getChoices())->toHaveCount(1);
    $choice = $response->getChoices()[0];
    expect($choice)->toBeInstanceOf(Choice::class); // Make sure Choice class is imported and used if ChatCompletionResponse uses it
    expect($choice->finishReason)->toBe(FinishReason::TOOL_CALLS);
    expect($choice->message)->toBeInstanceOf(Message::class);
    expect($choice->message->role)->toBe(Role::ASSISTANT);
    expect($choice->message->content)->toBe('I will check the weather in London.');
    expect($choice->message->toolCalls)->toHaveCount(1);
    $toolCall = $choice->message->toolCalls[0];
    expect($toolCall)->toBeInstanceOf(ToolCall::class);
    expect($toolCall->id)->toBe('call_123');
    expect($toolCall->name)->toBe('get_weather');
    expect($toolCall->arguments)->toBe(['location' => 'London']);
});

test('create completion validates model', function (): void { // Use closure arrow fn or regular function()
    // Expect a ValidationException to be thrown when model is empty
    expect(fn () => $this->chatService->createCompletion('', [Message::forUser('Hi')])) // Need valid messages
        ->toThrow(ValidationException::class, 'Model is required');
});

test('create completion validates messages', function (): void { // Use closure arrow fn or regular function()
    // Expect a ValidationException to be thrown when messages is empty
    expect(fn () => $this->chatService->createCompletion('test-model', []))
        ->toThrow(ValidationException::class, 'Messages are required');
});

test('create completion with response format', function (): void { // Use closure arrow fn or regular function()
    // Create test messages
    $messages = [
        Message::forUser('Extract info'),
    ];

    // Create response format
    $responseFormat = new ResponseFormat(ResponseFormatType::JSON_SCHEMA, [
        'type' => 'object',
        'properties' => ['name' => ['type' => 'string']],
        'required' => ['name'],
    ]);
    $expectedResponseFormatArray = $responseFormat->toArray();

    // Mock the API response
    $apiResponse = [
        'id' => 'chatcmpl-json',
        'object' => 'chat.completion',
        'created' => time(),
        'model' => 'test-model',
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => '{"name":"Test Name"}',
                ],
                'finish_reason' => 'stop',
                'logprobs' => null,
            ],
        ],
        'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5, 'total_tokens' => 10],
    ];

    // Set up the mock to expect a POST request with the response format
    $this->apiClient->shouldReceive('post')
        ->once()
        ->with('/api/v0/chat/completions', \Mockery::on(function ($data) use ($expectedResponseFormatArray) {
            return ($data['model'] ?? null) === 'test-model'
                && ! empty($data['messages'])
                && ($data['response_format'] ?? null) === $expectedResponseFormatArray;
        }))
        ->andReturn($apiResponse);

    // Call the createCompletion method
    $response = $this->chatService->createCompletion('test-model', $messages, null, $responseFormat);

    // Assert the response
    expect($response)->toBeInstanceOf(ChatCompletionResponse::class);
    expect($response->id)->toBe('chatcmpl-json');
    expect($response->getContent())->toBe('{"name":"Test Name"}');
});
