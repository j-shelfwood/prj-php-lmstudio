<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Laravel\Tools\QueueableToolExecutionHandler;

describe('QueueableToolExecutionHandler', function (): void {
    beforeEach(function (): void {
        $this->handler = new QueueableToolExecutionHandler(false);
    });

    test('set tool queueable sets the flag correctly', function (): void {
        $this->handler->setToolQueueable('test-tool', true);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($this->handler);
        $property = $reflection->getProperty('queueableTools');
        $property->setAccessible(true);

        $queueableTools = $property->getValue($this->handler);
        expect($queueableTools)->toHaveKey('test-tool');
        expect($queueableTools['test-tool'])->toBeTrue();
    });

    test('should queue tool returns correct value based on tool name', function (): void {
        $this->handler->setToolQueueable('test-tool', true);
        $this->handler->setToolQueueable('another-tool', false);

        expect($this->handler->shouldQueueTool('test-tool'))->toBeTrue();
        expect($this->handler->shouldQueueTool('another-tool'))->toBeFalse();
        expect($this->handler->shouldQueueTool('non-existent-tool'))->toBeFalse(); // Default is false
    });

    test('should queue tool returns default value for unknown tools', function (): void {
        // Create handler with default queueing enabled
        $handler = new QueueableToolExecutionHandler(true);

        expect($handler->shouldQueueTool('unknown-tool'))->toBeTrue();
    });

    test('on queued registers callback correctly', function (): void {
        $callback = function (): void {};
        $this->handler->onQueued($callback);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($this->handler);
        $property = $reflection->getProperty('eventHandler');
        $property->setAccessible(true);

        $eventHandler = $property->getValue($this->handler);
        expect($eventHandler->hasCallbacks('lmstudio.tool.queued'))->toBeTrue();
    });

    test('set queue connection sets the connection correctly', function (): void {
        $this->handler->setQueueConnection('test-connection');

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($this->handler);
        $property = $reflection->getProperty('queueConnection');
        $property->setAccessible(true);

        expect($property->getValue($this->handler))->toBe('test-connection');
    });
});
