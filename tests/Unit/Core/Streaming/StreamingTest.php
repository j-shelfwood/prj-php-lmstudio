<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Enum\Role;
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
            ->with('qwen2.5-7b-instruct-1m', Mockery::type('array'), Mockery::type('array'), Mockery::type('callable'))
            ->andReturnUsing(function ($model, $messages, $options, $callback) use ($streamingChunks): void {
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
        $this->eventHandler->registerCallback('tool_call', function ($name, $args, $id) use (&$toolCallEvents): void {
            $toolCallEvents[] = [
                'name' => $name,
                'args' => $args,
                'id' => $id,
            ];
        });

        // Set up the mock to call the callback with each chunk
        $this->chatService->shouldReceive('createCompletionStream')
            ->once()
            ->with('qwen2.5-7b-instruct-1m', Mockery::type('array'), Mockery::type('array'), Mockery::type('callable'))
            ->andReturnUsing(function ($model, $messages, $options, $callback) use ($streamingToolChunks): void {
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
        $this->eventHandler->registerCallback('error', function ($error) use (&$errorEvents): void {
            $errorEvents[] = $error;
        });

        // Set up the mock to throw an exception
        $this->chatService->shouldReceive('createCompletionStream')
            ->once()
            ->with('qwen2.5-7b-instruct-1m', Mockery::type('array'), Mockery::type('array'), Mockery::type('callable'))
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

        // Track chunk events
        $chunkEvents = [];
        $this->eventHandler->registerCallback('chunk', function ($chunk) use (&$chunkEvents): void {
            $chunkEvents[] = $chunk;
        });

        // Set up the mock to call the callback with each chunk
        $this->chatService->shouldReceive('createCompletionStream')
            ->once()
            ->with('qwen2.5-7b-instruct-1m', Mockery::type('array'), Mockery::type('array'), Mockery::type('callable'))
            ->andReturnUsing(function ($model, $messages, $options, $callback) use ($streamingChunks): void {
                foreach ($streamingChunks as $chunk) {
                    $callback($chunk);
                }
            });

        // Track streaming progress
        $progressText = '';
        $this->conversation->getStreamingResponse(function ($chunk) use (&$progressText): void {
            if (isset($chunk['choices'][0]['delta']['content'])) {
                $progressText .= $chunk['choices'][0]['delta']['content'];
            }
        });

        // Assert the chunk events were triggered
        expect($chunkEvents)->toHaveCount(5);

        // Assert the progress text was assembled correctly
        $expectedContent = "I'm sorry for any inconvenience, but as an AI, I don't have real-time capabilities to provide current weather updates or forecasts. Please check a reliable weather website or app for the most accurate information on the weather in London.";
        expect($progressText)->toBe($expectedContent);
    });
});
