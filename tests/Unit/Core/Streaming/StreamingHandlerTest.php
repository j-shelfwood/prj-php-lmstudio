<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;

describe('StreamingHandler', function (): void {
    it('on start callback', function (): void {
        $handler = new StreamingHandler;
        $called = false;
        $handler->onStart(function () use (&$called): void {
            $called = true;
        });

        $handler->handleChunk([
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

    it('on content callback', function (): void {
        $handler = new StreamingHandler;
        $receivedContent = '';
        $receivedBuffer = '';
        $isComplete = false;

        $handler->onContent(function ($content, $buffer, $complete) use (&$receivedContent, &$receivedBuffer, &$isComplete): void {
            $receivedContent = $content;
            $receivedBuffer = $buffer;
            $isComplete = $complete;
        });

        $handler->handleChunk([
            'choices' => [
                [
                    'delta' => [
                        'content' => 'Hello',
                    ],
                ],
            ],
        ]);

        expect($receivedContent)->toBe('Hello');
        expect($receivedBuffer)->toBe('Hello');
        expect($isComplete)->toBeFalse();

        $handler->handleChunk([
            'choices' => [
                [
                    'delta' => [
                        'content' => ' World',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        expect($receivedContent)->toBe(' World');
        expect($receivedBuffer)->toBe('Hello World');
        expect($isComplete)->toBeTrue();
    });

    it('on tool call callback', function (): void {
        $handler = new StreamingHandler;
        $receivedToolCall = null;
        $receivedIndex = null;
        $isComplete = false;

        $handler->onToolCall(function ($toolCall, $index, $complete) use (&$receivedToolCall, &$receivedIndex, &$isComplete): void {
            $receivedToolCall = $toolCall;
            $receivedIndex = $index;
            $isComplete = $complete;
        });

        $handler->handleChunk([
            'choices' => [
                [
                    'delta' => [
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"location":',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        expect($receivedToolCall['id'])->toBe('call_123');
        expect($receivedToolCall['type'])->toBe('function');
        expect($receivedToolCall['function']['name'])->toBe('get_weather');
        expect($receivedToolCall['function']['arguments'])->toBe('{"location":');
        expect($receivedIndex)->toBe(0);
        expect($isComplete)->toBeFalse();

        $handler->handleChunk([
            'choices' => [
                [
                    'delta' => [
                        'tool_calls' => [
                            [
                                'function' => [
                                    'arguments' => '"New York"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]);

        expect($receivedToolCall['id'])->toBe('call_123');
        expect($receivedToolCall['type'])->toBe('function');
        expect($receivedToolCall['function']['name'])->toBe('get_weather');
        expect($receivedToolCall['function']['arguments'])->toBe('{"location":"New York"}');
        expect($receivedIndex)->toBe(0);
        expect($isComplete)->toBeTrue();
    });

    it('on end callback', function (): void {
        $handler = new StreamingHandler;
        $receivedBuffer = '';
        $receivedToolCalls = [];

        $handler->onEnd(function ($buffer, $toolCalls) use (&$receivedBuffer, &$receivedToolCalls): void {
            $receivedBuffer = $buffer;
            $receivedToolCalls = $toolCalls;
        });

        $handler->handleChunk([
            'choices' => [
                [
                    'delta' => [
                        'content' => 'Hello',
                    ],
                ],
            ],
        ]);

        $handler->handleChunk([
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

        $handler->handleChunk([
            'choices' => [
                [
                    'delta' => [
                        'content' => ' World',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        expect($receivedBuffer)->toBe('Hello World');
        expect($receivedToolCalls)->toHaveCount(1);
        expect($receivedToolCalls[0]['function']['name'])->toBe('get_weather');
    });

    it('on error callback', function (): void {
        $handler = new StreamingHandler;
        $receivedError = null;
        $receivedBuffer = '';
        $receivedToolCalls = [];

        $handler->onError(function ($error, $buffer, $toolCalls) use (&$receivedError, &$receivedBuffer, &$receivedToolCalls): void {
            $receivedError = $error;
            $receivedBuffer = $buffer;
            $receivedToolCalls = $toolCalls;
        });

        $handler->handleChunk([
            'choices' => [
                [
                    'delta' => [
                        'content' => 'Hello',
                    ],
                ],
            ],
        ]);

        $error = new \Exception('Test error');
        $handler->handleError($error);

        expect($receivedError)->toBe($error);
        expect($receivedBuffer)->toBe('Hello');
        expect($receivedToolCalls)->toHaveCount(0);
    });

    it('reset', function (): void {
        $handler = new StreamingHandler;

        $handler->handleChunk([
            'choices' => [
                [
                    'delta' => [
                        'content' => 'Hello',
                    ],
                ],
            ],
        ]);

        $handler->handleChunk([
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

        $handler->reset();

        expect($handler->getBuffer())->toBe('');
        expect($handler->getToolCalls())->toHaveCount(0);

        $handler->handleChunk([
            'choices' => [
                [
                    'delta' => [
                        'content' => 'New content',
                    ],
                ],
            ],
        ]);

        expect($handler->getBuffer())->toBe('New content');
    });
});
