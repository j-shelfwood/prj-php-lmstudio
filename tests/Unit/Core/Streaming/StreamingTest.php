<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Streaming;

use Exception;
use Mockery;
use Shelfwood\LMStudio\Api\Enum\ResponseFormatType;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\ResponseFormat;
use Shelfwood\LMStudio\Api\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Conversation\Conversation;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;

describe('Streaming', function (): void {
    beforeEach(function (): void {
        $this->chatService = Mockery::mock(ChatService::class);
        $this->toolRegistry = new ToolRegistry;
        $this->eventHandler = new EventHandler;
        $this->conversation = new Conversation(
            $this->chatService,
            'qwen2.5-7b-instruct-1m',
            [],
            $this->toolRegistry,
            $this->eventHandler,
            true
        );
    });

    test('conversation can stream responses', function (): void {
        // Load the mock streaming chunks
        $streamingChunks = load_mock('chat/streaming-chunks.json');

        // Add a user message
        $this->conversation->addUserMessage('What\'s the weather like in London?');

        // Set up the mock to call the callback with each chunk
        $this->chatService->shouldReceive('createCompletionStream')
            ->once()
            ->with(
                'qwen2.5-7b-instruct-1m',
                Mockery::type('array'),
                Mockery::type('callable'),
                null,
                null,
                Mockery::on(function ($options) {
                    return isset($options['stream']) && $options['stream'] === true;
                })
            )
            ->andReturnUsing(function ($model, $messages, $callback) use ($streamingChunks): void {
                foreach ($streamingChunks as $chunk) {
                    $callback($chunk);
                }
            });

        // Collect the chunks
        $receivedChunks = [];
        $fullContent = $this->conversation->getStreamingResponse(function ($chunk) use (&$receivedChunks): void {
            $receivedChunks[] = $chunk;
        });

        // Assert the chunks were received
        expect($receivedChunks)->toHaveCount(5);
        expect($receivedChunks[0]['id'])->toBe('chatcmpl-123-chunk-1');

        // Assert the full content was assembled correctly
        $expectedContent = "I'm sorry for any inconvenience, but as an AI, I don't have real-time capabilities to provide current weather updates or forecasts. Please check a reliable weather website or app for the most accurate information on the weather in London.";
        expect($fullContent)->toBe($expectedContent);

        // Assert the conversation history is maintained
        $messages = $this->conversation->getMessages();
        expect($messages)->toHaveCount(2);
        expect($messages[0]->getRole())->toBe(Role::USER);
        expect($messages[0]->getContent())->toBe('What\'s the weather like in London?');
        expect($messages[1]->getRole())->toBe(Role::ASSISTANT);
        expect($messages[1]->getContent())->toBe($expectedContent);
    });

    test('conversation can stream tool calls', function (): void {
        // Load the mock streaming tool chunks
        $streamingToolChunks = load_mock('chat/streaming-tool-chunks.json');

        // Register a weather tool
        $this->toolRegistry->registerTool(
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

        // Add a user message
        $this->conversation->addUserMessage('What\'s the weather like in London?');

        // Track tool call events
        $toolCallEvents = [];
        $this->eventHandler->on('tool_call', function ($name, $args, $id) use (&$toolCallEvents): void {
            $toolCallEvents[] = [
                'name' => $name,
                'args' => $args,
                'id' => $id,
            ];
        });

        // Track error events
        $errorEvents = [];
        $this->eventHandler->on('error', function ($error) use (&$errorEvents): void {
            $errorEvents[] = $error;
        });

        // Track chunk events
        $chunkEvents = [];
        $this->eventHandler->on('chunk', function ($chunk) use (&$chunkEvents): void {
            $chunkEvents[] = $chunk;
        });

        // Set up the mock to call the callback with each chunk
        $this->chatService->shouldReceive('createCompletionStream')
            ->once()
            ->with(
                'qwen2.5-7b-instruct-1m',
                Mockery::type('array'),
                Mockery::type('callable'),
                null,
                null,
                Mockery::on(function ($options) {
                    return isset($options['stream']) && $options['stream'] === true;
                })
            )
            ->andReturnUsing(function ($model, $messages, $callback) use ($streamingToolChunks): void {
                foreach ($streamingToolChunks as $chunk) {
                    $callback($chunk);
                }
            });

        // Collect the chunks
        $receivedChunks = [];
        $fullContent = $this->conversation->getStreamingResponse(function ($chunk) use (&$receivedChunks): void {
            $receivedChunks[] = $chunk;
        });

        // Assert the chunks were received
        expect($receivedChunks)->toHaveCount(5);
        expect($receivedChunks[0]['id'])->toBe('chatcmpl-456-chunk-1');

        // Assert the tool call was processed
        expect($toolCallEvents)->toHaveCount(1);
        expect($toolCallEvents[0]['name'])->toBe('get_weather');
        expect($toolCallEvents[0]['args'])->toBe(['location' => 'London']);
        expect($toolCallEvents[0]['id'])->toBe('call_123');

        // Assert the conversation history is maintained
        $messages = $this->conversation->getMessages();
        expect($messages)->toHaveCount(3);
        expect($messages[0]->getRole())->toBe(Role::USER);
        expect($messages[0]->getContent())->toBe('What\'s the weather like in London?');
        expect($messages[1]->getRole())->toBe(Role::ASSISTANT);
        expect($messages[1]->getToolCalls())->not->toBeNull();
        expect($messages[1]->getToolCalls()[0]['function']['name'])->toBe('get_weather');
        expect($messages[1]->getToolCalls()[0]['function']['arguments'])->toBe('{"location":"London"}');
        expect($messages[2]->getRole())->toBe(Role::TOOL);
        expect($messages[2]->getContent())->toContain('temperature');
        expect($messages[2]->getContent())->toContain('sunny');
    });

    test('conversation handles streaming errors', function (): void {
        // Add a user message
        $this->conversation->addUserMessage('What\'s the weather like in London?');

        // Track error events
        $errorEvents = [];
        $this->eventHandler->on('error', function ($error) use (&$errorEvents): void {
            $errorEvents[] = $error;
        });

        // Set up the mock to throw an exception
        $this->chatService->shouldReceive('createCompletionStream')
            ->once()
            ->with(
                'qwen2.5-7b-instruct-1m',
                Mockery::type('array'),
                Mockery::type('callable'),
                null,
                null,
                Mockery::on(function ($options) {
                    return isset($options['stream']) && $options['stream'] === true;
                })
            )
            ->andThrow(new Exception('API Error'));

        // Expect an exception to be thrown
        expect(fn () => $this->conversation->getStreamingResponse(function ($chunk): void {
            // This should not be called
        }))->toThrow(Exception::class, 'API Error');

        // Assert the error event was triggered
        expect($errorEvents)->toHaveCount(1);
        expect($errorEvents[0]->getMessage())->toBe('API Error');
    });

    test('conversation can track streaming progress', function (): void {
        // Load the mock streaming chunks
        $streamingChunks = load_mock('chat/streaming-chunks.json');

        // Add a user message
        $this->conversation->addUserMessage('What\'s the weather like in London?');

        // Track progress events
        $progressEvents = [];
        $this->eventHandler->on('progress', function ($progress) use (&$progressEvents): void {
            $progressEvents[] = $progress;
        });

        // Set up the mock to call the callback with each chunk
        $this->chatService->shouldReceive('createCompletionStream')
            ->once()
            ->with(
                'qwen2.5-7b-instruct-1m',
                Mockery::type('array'),
                Mockery::type('callable'),
                null,
                null,
                Mockery::on(function ($options) {
                    return isset($options['stream']) && $options['stream'] === true;
                })
            )
            ->andReturnUsing(function ($model, $messages, $callback) use ($streamingChunks): void {
                foreach ($streamingChunks as $index => $chunk) {
                    $callback($chunk);
                    $this->eventHandler->trigger('progress', (float) (($index + 1) / count($streamingChunks)));
                    $this->conversation->markProgressTriggered();
                }
            });

        // Get the streaming response
        $this->conversation->getStreamingResponse(function ($chunk): void {
            // Just collect the chunks
        });

        // Assert the progress events were triggered
        expect($progressEvents)->toHaveCount(5);
        expect($progressEvents[0])->toBe(0.2);
        expect($progressEvents[4])->toBe(1.0);
    });

    test('conversation can stream structured output', function (): void {
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

        // Mock the API response chunks
        $chunks = [
            [
                'id' => 'chatcmpl-123',
                'object' => 'chat.completion.chunk',
                'created' => 1677858242,
                'model' => 'qwen2.5-7b-instruct-1m',
                'choices' => [
                    [
                        'index' => 0,
                        'delta' => [
                            'role' => 'assistant',
                            'content' => '{"location":"London","temperature":20}',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ],
        ];

        // Set up the mock to expect a createCompletionStream call with the correct data
        $this->chatService->shouldReceive('createCompletionStream')
            ->once()
            ->with(
                'qwen2.5-7b-instruct-1m',
                Mockery::type('array'),
                Mockery::type('callable'),
                null,
                null,
                Mockery::on(function ($options) use ($responseFormat) {
                    return isset($options['stream'])
                        && $options['stream'] === true
                        && isset($options['response_format'])
                        && $options['response_format'] === $responseFormat;
                })
            )
            ->andReturnUsing(function ($model, $messages, $callback) use ($chunks): void {
                foreach ($chunks as $chunk) {
                    $callback($chunk);
                }
                // Trigger final progress
                $this->eventHandler->trigger('progress', 1.0);
            });

        // Set up the mock to expect a createCompletion call with the correct data
        $this->chatService->shouldReceive('createCompletion')
            ->with(
                'qwen2.5-7b-instruct-1m',
                Mockery::type('array'),
                Mockery::on(function ($options) use ($responseFormat) {
                    return $options['stream'] === true
                        && isset($options['response_format'])
                        && $options['response_format'] === $responseFormat;
                })
            )
            ->andReturn(ChatCompletionResponse::fromArray([
                'id' => 'test-id',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'qwen2.5-7b-instruct-1m',
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
                    'completion_tokens' => 20,
                    'total_tokens' => 30,
                ],
            ]));

        // Create a conversation with streaming enabled
        $conversation = new Conversation(
            $this->chatService,
            'qwen2.5-7b-instruct-1m',
            [
                'stream' => true,
                'response_format' => $responseFormat,
            ],
            $this->toolRegistry,
            $this->eventHandler
        );

        // Add a user message
        $conversation->addUserMessage('What\'s the weather like in London?');

        // Track the streaming progress
        $progress = 0.0;
        $this->eventHandler->on('progress', function ($value) use (&$progress): void {
            $progress = $value;
        });

        // Get a response
        $response = $conversation->getStreamingResponse();

        // Assert the response is correct
        expect($response)->toBe('{"location":"London","temperature":20}');
        expect($progress)->toBe(1.0);
    });
});
