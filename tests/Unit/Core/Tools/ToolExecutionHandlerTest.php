<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Core\Tools\ToolExecutionHandler;

describe('StreamingHandler', function (): void {
    it('on received callback', function (): void {
        $handler = new ToolExecutionHandler;
        $receivedToolCall = null;

        $handler->onReceived(function ($toolCall) use (&$receivedToolCall): void {
            $receivedToolCall = $toolCall;
        });

        $toolCall = [
            'id' => 'call_123',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"location":"New York"}',
            ],
        ];

        $handler->handleReceived($toolCall);

        expect($receivedToolCall)->toBe($toolCall);
    });

    it('on executing callback', function (): void {
        $handler = new ToolExecutionHandler;
        $receivedToolCall = null;
        $receivedExecutor = null;

        $handler->onExecuting(function ($toolCall, $executor) use (&$receivedToolCall, &$receivedExecutor): void {
            $receivedToolCall = $toolCall;
            $receivedExecutor = $executor;
        });

        $toolCall = [
            'id' => 'call_123',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"location":"New York"}',
            ],
        ];

        $executor = function ($args) {
            return "Weather for {$args['location']}: Sunny";
        };

        $handler->handleExecuting($toolCall, $executor);

        expect($receivedToolCall)->toBe($toolCall);
        expect($receivedExecutor)->toBe($executor);
        expect(is_callable($receivedExecutor))->toBeTrue();

        // Test that the executor works
        $result = $receivedExecutor(['location' => 'New York']);
        expect($result)->toBe('Weather for New York: Sunny');
    });

    it('on executed callback', function (): void {
        $handler = new ToolExecutionHandler;
        $receivedToolCall = null;
        $receivedResult = null;

        $handler->onExecuted(function ($toolCall, $result) use (&$receivedToolCall, &$receivedResult): void {
            $receivedToolCall = $toolCall;
            $receivedResult = $result;
        });

        $toolCall = [
            'id' => 'call_123',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"location":"New York"}',
            ],
        ];

        $result = 'Weather for New York: Sunny';

        $handler->handleExecuted($toolCall, $result);

        expect($receivedToolCall)->toBe($toolCall);
        expect($receivedResult)->toBe($result);
    });

    it('on error callback', function (): void {
        $handler = new ToolExecutionHandler;
        $receivedToolCall = null;
        $receivedError = null;

        $handler->onError(function ($toolCall, $error) use (&$receivedToolCall, &$receivedError): void {
            $receivedToolCall = $toolCall;
            $receivedError = $error;
        });

        $toolCall = [
            'id' => 'call_123',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"location":"New York"}',
            ],
        ];

        $error = new \Exception('Test error');

        $handler->handleError($toolCall, $error);

        expect($receivedToolCall)->toBe($toolCall);
        expect($receivedError)->toBe($error);
    });

    it('chained callbacks', function (): void {
        $handler = new ToolExecutionHandler;
        $receivedStates = [];

        $handler
            ->onReceived(function ($toolCall) use (&$receivedStates): void {
                $receivedStates[] = 'received';
            })
            ->onExecuting(function ($toolCall, $executor) use (&$receivedStates): void {
                $receivedStates[] = 'executing';
            })
            ->onExecuted(function ($toolCall, $result) use (&$receivedStates): void {
                $receivedStates[] = 'executed';
            })
            ->onError(function ($toolCall, $error) use (&$receivedStates): void {
                $receivedStates[] = 'error';
            });

        $toolCall = [
            'id' => 'call_123',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"location":"New York"}',
            ],
        ];

        $executor = function ($args) {
            return "Weather for {$args['location']}: Sunny";
        };

        $result = 'Weather for New York: Sunny';
        $error = new \Exception('Test error');

        // Simulate the full lifecycle
        $handler->handleReceived($toolCall);
        $handler->handleExecuting($toolCall, $executor);
        $handler->handleExecuted($toolCall, $result);
        $handler->handleError($toolCall, $error);

        expect($receivedStates)->toBe(['received', 'executing', 'executed', 'error']);
    });
});
