<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Api\Enum\ResponseFormatType;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Exception\ValidationException;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\ResponseFormat;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;
use Shelfwood\LMStudio\Api\Service\ChatService;

describe('ToolExecutionHandler', function (): void {
    beforeEach(function (): void {
        $this->apiClient = \Mockery::mock(ApiClientInterface::class);
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
        $this->chatService->createCompletionStream('test-model', $messages, $callback);
    });

    test('create completion stream with tools', function (): void {
        // Create test messages
        $messages = [
            new Message(Role::USER, 'What\'s the weather like in London?'),
        ];

        // Create tool parameters
        $parameters = new ToolParameters;
        $parameters->addProperty('location', new ToolParameter('string', 'The location to get weather for'));
        $parameters->addRequired('location'); // Ensure required is added

        // Create tool definition and tool object
        $toolDefinition = new ToolDefinition('get_weather', 'Get the weather for a location', $parameters);
        $tool = new Tool(ToolType::FUNCTION, $toolDefinition);

        // Expected arrays based on models
        $expectedMessagesArray = array_map(fn (Message $m) => $m->toArray(), $messages);
        $expectedToolsArray = [$tool->toArray()];

        // Mock the API response chunks
        $chunks = [
            [
                'id' => 'chatcmpl-123',
                'object' => 'chat.completion.chunk',
                'created' => 1677858242,
                'model' => 'test-model',
                'choices' => [
                    [
                        'index' => 0,
                        'delta' => [
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
            ],
        ];

        // Set up the mock to expect a POST request with the correct data
        $this->apiClient->shouldReceive('postStream')
            ->once()
            ->with('/api/v0/chat/completions', \Mockery::on(function ($data) use ($expectedMessagesArray, $expectedToolsArray) {
                // Basic checks
                if ($data['model'] !== 'test-model') {
                    return false;
                }

                if (! isset($data['stream']) || $data['stream'] !== true) {
                    return false;
                }

                if ($data['messages'] !== $expectedMessagesArray) {
                    return false;
                }

                if (! isset($data['tools']) || ! is_array($data['tools'])) {
                    return false;
                } // Check tools exist

                if (count($data['tools']) !== count($expectedToolsArray)) {
                    return false;
                } // Check count

                // Compare tools structure using JSON encoding
                $actualToolsJson = json_encode($data['tools']);
                $expectedToolsJson = json_encode($expectedToolsArray);

                if ($actualToolsJson === false || $expectedToolsJson === false) {
                    return false;
                }

                return $actualToolsJson === $expectedToolsJson;
            }), \Mockery::type('callable'))
            ->andReturnUsing(function ($endpoint, $data, $callback) use ($chunks): void {
                foreach ($chunks as $chunk) {
                    $callback($chunk);
                }
            });

        // Create a callback to collect the chunks
        $receivedChunks = [];
        $callback = function ($chunk) use (&$receivedChunks): void {
            $receivedChunks[] = $chunk;
        };

        // Call the createCompletionStream method
        $this->chatService->createCompletionStream('test-model', $messages, $callback, [$tool]); // Pass the Tool object

        // Assert that we received the expected chunks
        expect($receivedChunks)->toHaveCount(1);
        expect($receivedChunks[0]['id'])->toBe('chatcmpl-123');
        expect($receivedChunks[0]['object'])->toBe('chat.completion.chunk');
        expect($receivedChunks[0]['choices'][0]['delta']['role'])->toBe('assistant');
        expect($receivedChunks[0]['choices'][0]['delta']['content'])->toBe('I will check the weather in London.');
        expect($receivedChunks[0]['choices'][0]['delta']['tool_calls'][0]['id'])->toBe('call_123');
        expect($receivedChunks[0]['choices'][0]['delta']['tool_calls'][0]['type'])->toBe('function');
        expect($receivedChunks[0]['choices'][0]['delta']['tool_calls'][0]['function']['name'])->toBe('get_weather');
        expect($receivedChunks[0]['choices'][0]['delta']['tool_calls'][0]['function']['arguments'])->toBe('{"location":"London"}');
    });

    test('create completion stream validates model', function (): void {
        // Create a callback
        $callback = function ($chunk): void {
            // Callback function
        };

        // Expect a ValidationException to be thrown when model is empty
        expect(fn () => $this->chatService->createCompletionStream('', [], $callback))
            ->toThrow(ValidationException::class, 'Model is required');
    });

    test('create completion stream validates messages', function (): void {
        // Create a callback
        $callback = function ($chunk): void {
            // Callback function
        };

        // Expect a ValidationException to be thrown when messages is empty
        expect(fn () => $this->chatService->createCompletionStream('test-model', [], $callback))
            ->toThrow(ValidationException::class, 'Messages are required');
    });

    test('create completion stream validates callback', function (): void {
        // Create test messages
        $messages = [
            Message::forUser('Hello'),
        ];

        // Expect a ValidationException to be thrown when callback is null
        expect(fn () => $this->chatService->createCompletionStream('test-model', $messages, null))
            ->toThrow(\TypeError::class, 'must be of type callable');
    });

    test('create completion stream ensures stream option is set', function (): void {
        // Create test messages
        $messages = [
            Message::forUser('Hello'),
        ];

        // Mock the API response chunks
        $chunks = [
            [
                'id' => 'chatcmpl-123',
                'object' => 'chat.completion.chunk',
                'created' => 1677858242,
                'model' => 'test-model',
                'choices' => [
                    [
                        'index' => 0,
                        'delta' => [
                            'role' => 'assistant',
                            'content' => 'Hello!',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ],
        ];

        // Set up the mock to expect a POST request with stream=true
        $this->apiClient->shouldReceive('postStream')
            ->once()
            ->with('/api/v0/chat/completions', \Mockery::on(function ($data) {
                return isset($data['stream']) && $data['stream'] === true
                    && $data['model'] === 'test-model'
                    && is_array($data['messages']);
            }), \Mockery::type('callable'))
            ->andReturnUsing(function ($endpoint, $data, $callback) use ($chunks): void {
                foreach ($chunks as $chunk) {
                    $callback($chunk);
                }
            });

        // Create a callback to collect the chunks
        $receivedChunks = [];
        $callback = function ($chunk) use (&$receivedChunks): void {
            $receivedChunks[] = $chunk;
        };

        // Call the createCompletionStream method with stream=false in options
        $this->chatService->createCompletionStream('test-model', $messages, $callback, null, null, ['stream' => false]);

        // Assert that we received the expected chunks
        expect($receivedChunks)->toHaveCount(1);
        expect($receivedChunks[0]['id'])->toBe('chatcmpl-123');
        expect($receivedChunks[0]['object'])->toBe('chat.completion.chunk');
        expect($receivedChunks[0]['choices'][0]['delta']['role'])->toBe('assistant');
        expect($receivedChunks[0]['choices'][0]['delta']['content'])->toBe('Hello!');
    });

    test('create completion stream with response format', function (): void {
        // Create test messages
        $messages = [
            new Message(Role::USER, 'Tell me a joke.'),
        ];

        // Create a response format
        $jsonSchema = [
            'name' => 'joke_response',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'joke' => [
                        'type' => 'string',
                    ],
                ],
                'required' => ['joke'],
            ],
        ];

        $responseFormat = new ResponseFormat(ResponseFormatType::JSON_SCHEMA, $jsonSchema);

        // Create a callback
        $callback = function ($chunk): void {
            // Callback function
        };

        // Set up the mock to expect a POST request with the correct data
        $this->apiClient->shouldReceive('postStream')
            ->once()
            ->with('/api/v0/chat/completions', [
                'model' => 'test-model',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Tell me a joke.',
                    ],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => $jsonSchema,
                ],
                'stream' => true,
            ], $callback);

        // Call the createCompletionStream method
        $this->chatService->createCompletionStream('test-model', $messages, $callback, null, $responseFormat);
    });
});
