<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Queue as QueueDispatcherContract;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\Laravel\Tools\QueueableToolExecutionHandler;

describe('QueueableToolExecutionHandler', function (): void {
    beforeEach(function (): void {
        $this->toolRegistryMock = Mockery::mock(ToolRegistry::class);
        $this->eventHandlerMock = Mockery::mock(EventHandler::class);
        $this->queueDispatcherMock = Mockery::mock(QueueDispatcherContract::class);

        // Inject mocks into the handler
        $this->handler = new QueueableToolExecutionHandler(
            $this->toolRegistryMock,
            $this->eventHandlerMock,
            $this->queueDispatcherMock,
            false // queueByDefault
        );
    });

    test('set tool queueable sets the flag correctly', function (): void {
        $this->handler->setToolQueueable('test-tool', true);

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
        expect($this->handler->shouldQueueTool('non-existent-tool'))->toBeFalse(); // Default is false (set in beforeEach)
    });

    test('should queue tool returns default value for unknown tools', function (): void {
        // Create handler with default queueing enabled
        $handlerWithDefault = new QueueableToolExecutionHandler(
            $this->toolRegistryMock, $this->eventHandlerMock, $this->queueDispatcherMock, true
        );
        expect($handlerWithDefault->shouldQueueTool('unknown-tool'))->toBeTrue();
    });

    test('on queued registers callback correctly', function (): void {
        $callback = function (): void {};

        // Expect the event handler mock's `on` method to be called
        $this->eventHandlerMock->shouldReceive('on')
            ->once()
            ->with('lmstudio.tool.queued', $callback);

        $this->handler->onQueued($callback);
        // No need for reflection - we check the mock was called correctly
    });

    test('set queue connection sets the connection correctly', function (): void {
        $this->handler->setQueueConnection('test-connection');

        $reflection = new \ReflectionClass($this->handler);
        $property = $reflection->getProperty('queueConnection');
        $property->setAccessible(true);
        expect($property->getValue($this->handler))->toBe('test-connection');
    });

    // Add tests for the execute method if needed (checking dispatch etc.)
});
