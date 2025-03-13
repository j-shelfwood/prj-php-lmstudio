<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Builder\ConversationBuilder;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tools\ToolExecutionHandler;

describe('ConversationBuilderStreamingHandler', function (): void {
    it('with streaming handler', function (): void {
        $chatService = Mockery::mock(ChatService::class);
        $builder = new ConversationBuilder($chatService, 'gpt-4');
        $streamingHandler = new StreamingHandler;
        $builder->withStreamingHandler($streamingHandler);
        $conversation = $builder->build();
        expect($conversation->isStreaming())->toBeTrue();
        expect($conversation->getStreamingHandler())->toBe($streamingHandler);
    });

    it('with tool execution handler', function (): void {
        $chatService = Mockery::mock(ChatService::class);
        $builder = new ConversationBuilder($chatService, 'gpt-4');
        $toolExecutionHandler = new ToolExecutionHandler;
        $builder->withToolExecutionHandler($toolExecutionHandler);
        $conversation = $builder->build();
        expect($conversation->getToolExecutionHandler())->toBe($toolExecutionHandler);
    });

    it('on stream start creates handler', function (): void {
        $chatService = Mockery::mock(ChatService::class);
        $builder = new ConversationBuilder($chatService, 'gpt-4');
        $called = false;
        $builder->onStreamStart(function () use (&$called): void {
            $called = true;
        });
        $conversation = $builder->build();
        expect($conversation->isStreaming())->toBeTrue();
        expect($conversation->getStreamingHandler())->not->toBeNull();
        $conversation->getStreamingHandler()->handleChunk([
            'choices' => [
                [
                    'delta' => [
                        'content' => 'Hello',
                    ],
                ],
            ],
        ]);
        expect($called)->toBeTrue();
    });

    it('on stream content creates handler', function (): void {
        $chatService = Mockery::mock(ChatService::class);
        $builder = new ConversationBuilder($chatService, 'gpt-4');
        $receivedContent = '';
        $builder->onStreamContent(function ($content) use (&$receivedContent): void {
            $receivedContent = $content;
        });
        $conversation = $builder->build();
        expect($conversation->isStreaming())->toBeTrue();
        expect($conversation->getStreamingHandler())->not->toBeNull();
        $conversation->getStreamingHandler()->handleChunk([
            'choices' => [
                [
                    'delta' => [
                        'content' => 'Hello',
                    ],
                ],
            ],
        ]);
        expect($receivedContent)->toBe('Hello');
    });

    it('on stream tool call creates handler', function (): void {
        $chatService = Mockery::mock(ChatService::class);
        $builder = new ConversationBuilder($chatService, 'gpt-4');
        $receivedToolCall = null;
        $builder->onStreamToolCall(function ($toolCall) use (&$receivedToolCall): void {
            $receivedToolCall = $toolCall;
        });
        $conversation = $builder->build();
        expect($conversation->isStreaming())->toBeTrue();
        expect($conversation->getStreamingHandler())->not->toBeNull();
        $conversation->getStreamingHandler()->handleChunk([
            'choices' => [
                [
                    'delta' => [
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"location":"New York"}',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        expect($receivedToolCall)->not->toBeNull();
        expect($receivedToolCall['function']['name'])->toBe('get_weather');
    });

    it('on stream end creates handler', function (): void {
        $chatService = Mockery::mock(ChatService::class);
        $builder = new ConversationBuilder($chatService, 'gpt-4');
        $called = false;
        $builder->onStreamEnd(function () use (&$called): void {
            $called = true;
        });
        $conversation = $builder->build();
        expect($conversation->isStreaming())->toBeTrue();
        expect($conversation->getStreamingHandler())->not->toBeNull();
        $conversation->getStreamingHandler()->handleChunk([
            'choices' => [
                [
                    'delta' => [
                        'content' => 'Hello',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);
        expect($called)->toBeTrue();
    });

    it('on stream error creates handler', function (): void {
        $chatService = Mockery::mock(ChatService::class);
        $builder = new ConversationBuilder($chatService, 'gpt-4');
        $receivedError = null;
        $builder->onStreamError(function ($error) use (&$receivedError): void {
            $receivedError = $error;
        });
        $conversation = $builder->build();
        expect($conversation->isStreaming())->toBeTrue();
        expect($conversation->getStreamingHandler())->not->toBeNull();
        $error = new \Exception('Test error');
        $conversation->getStreamingHandler()->handleError($error);
        expect($receivedError)->toBe($error);
    });

    it('on tool received creates handler', function (): void {
        $chatService = Mockery::mock(ChatService::class);
        $builder = new ConversationBuilder($chatService, 'gpt-4');
        $receivedToolCall = null;
        $builder->onToolReceived(function ($toolCall) use (&$receivedToolCall): void {
            $receivedToolCall = $toolCall;
        });
        $conversation = $builder->build();
        expect($conversation->getToolExecutionHandler())->not->toBeNull();
        $toolCall = [
            'id' => 'call_123',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"location":"New York"}',
            ],
        ];
        $conversation->getToolExecutionHandler()->handleReceived($toolCall);
        expect($receivedToolCall)->toBe($toolCall);
    });

    it('on tool executing creates handler', function (): void {
        $chatService = Mockery::mock(ChatService::class);
        $builder = new ConversationBuilder($chatService, 'gpt-4');
        $receivedToolCall = null;
        $builder->onToolExecuting(function ($toolCall) use (&$receivedToolCall): void {
            $receivedToolCall = $toolCall;
        });
        $conversation = $builder->build();
        expect($conversation->getToolExecutionHandler())->not->toBeNull();
        $toolCall = [
            'id' => 'call_123',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"location":"New York"}',
            ],
        ];
        $executor = function () {
            return 'Weather: Sunny';
        };
        $conversation->getToolExecutionHandler()->handleExecuting($toolCall, $executor);
        expect($receivedToolCall)->toBe($toolCall);
    });

    it('on tool executed creates handler', function (): void {
        $chatService = Mockery::mock(ChatService::class);
        $builder = new ConversationBuilder($chatService, 'gpt-4');
        $receivedResult = null;
        $builder->onToolExecuted(function ($toolCall, $result) use (&$receivedResult): void {
            $receivedResult = $result;
        });
        $conversation = $builder->build();
        expect($conversation->getToolExecutionHandler())->not->toBeNull();
        $toolCall = [
            'id' => 'call_123',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"location":"New York"}',
            ],
        ];
        $result = 'Weather: Sunny';
        $conversation->getToolExecutionHandler()->handleExecuted($toolCall, $result);
        expect($receivedResult)->toBe($result);
    });

    it('on tool error creates handler', function (): void {
        $chatService = Mockery::mock(ChatService::class);
        $builder = new ConversationBuilder($chatService, 'gpt-4');
        $receivedError = null;
        $builder->onToolError(function ($toolCall, $error) use (&$receivedError): void {
            $receivedError = $error;
        });
        $conversation = $builder->build();
        expect($conversation->getToolExecutionHandler())->not->toBeNull();
        $toolCall = [
            'id' => 'call_123',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"location":"New York"}',
            ],
        ];
        $error = new \Exception('Test error');
        $conversation->getToolExecutionHandler()->handleError($toolCall, $error);
        expect($receivedError)->toBe($error);
    });
});
