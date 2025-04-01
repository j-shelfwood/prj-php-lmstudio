<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\Laravel\Jobs\ExecuteToolJob;
use Shelfwood\LMStudio\LMStudioFactory;

describe('ExecuteToolJob', function (): void {
    test('job implements should queue', function (): void {
        $job = new ExecuteToolJob('test-tool', [], 'test-id');
        expect($job)->toBeInstanceOf(ShouldQueue::class);
    });

    test('job constructor sets properties correctly', function (): void {
        $job = new ExecuteToolJob(
            'test-tool',
            ['arg' => 'value'],
            'test-id',
            5,
            60
        );
        // Reflection tests for protected props
        $reflection = new \ReflectionClass($job);
        $toolNameProp = $reflection->getProperty('toolName');
        $toolNameProp->setAccessible(true);
        $paramsProp = $reflection->getProperty('parameters');
        $paramsProp->setAccessible(true);
        $toolCallIdProp = $reflection->getProperty('toolCallId');
        $toolCallIdProp->setAccessible(true);
        expect($toolNameProp->getValue($job))->toBe('test-tool');
        expect($paramsProp->getValue($job))->toBe(['arg' => 'value']);
        expect($toolCallIdProp->getValue($job))->toBe('test-id');
        // Public props
        expect($job->tries)->toBe(5);
        expect($job->timeout)->toBe(60);
    });

    test('job constructor sets default values correctly', function (): void {
        config(['lmstudio.queue.tries' => 3, 'lmstudio.queue.timeout' => 60, 'lmstudio.queue.queue' => 'default', 'lmstudio.queue.connection' => null]);
        $job = new ExecuteToolJob('test-tool', [], 'test-id');
        expect($job->tries)->toBe(3);
        expect($job->timeout)->toBe(60);
        expect($job->queue)->toBe('default');
        expect($job->connection)->toBeNull();
    });

    beforeEach(function (): void {
        // Mock factory and its dependencies (registry, handler)
        $this->factoryMock = Mockery::mock(LMStudioFactory::class);
        $this->toolRegistryMock = Mockery::mock(ToolRegistry::class);
        $this->eventHandlerMock = Mockery::mock(EventHandler::class)->makePartial(); // Allow trigger

        // Configure factory mock
        $this->factoryMock->shouldReceive('getToolRegistry')->andReturn($this->toolRegistryMock);
        $this->factoryMock->shouldReceive('getEventHandler')->andReturn($this->eventHandlerMock);

        // Bind mock factory to container for the job's handle method
        // Note: In full Laravel tests, this binding might happen differently
        $this->app->instance(LMStudioFactory::class, $this->factoryMock);
    });

    test('job handle method executes tool correctly', function (): void {
        $toolName = 'test-tool';
        $params = ['arg' => 'value'];
        $toolCallId = 'test-id';
        $expectedResult = ['result' => 'success'];

        $this->toolRegistryMock->shouldReceive('hasTool')->once()->with($toolName)->andReturn(true);
        // Expect executeTool to be called on the registry
        $this->toolRegistryMock->shouldReceive('executeTool')
            ->once()
            ->with($toolName, $params, $toolCallId)
            ->andReturn($expectedResult);

        // The ToolExecutor (used internally by registry->executeTool) should trigger events.
        // We don't mock the executor directly here, but trust the registry delegates.
        // So, we don't expect direct trigger calls on eventHandlerMock from THIS test context.
        $this->eventHandlerMock->shouldReceive('trigger')->never();

        $job = new ExecuteToolJob($toolName, $params, $toolCallId);
        $job->handle($this->factoryMock); // Pass mock factory

        // No direct assertions on callbacks needed, as event triggering is internal to executeTool
    });

    test('job handle method triggers error event on tool execution exception', function (): void {
        $toolName = 'test-tool';
        $params = ['arg' => 'value'];
        $toolCallId = 'test-id';
        $exception = new \Exception('Tool execution failed');

        $this->toolRegistryMock->shouldReceive('hasTool')->once()->with($toolName)->andReturn(true);
        $this->toolRegistryMock->shouldReceive('executeTool')
            ->once()
            ->with($toolName, $params, $toolCallId)
            ->andThrow($exception);

        // ToolRegistry::executeTool should trigger the error event via ToolExecutor
        // We verify this behaviour in ToolRegistry/ToolExecutor tests, not here.
        // So, no direct trigger expectation on eventHandlerMock needed here.
        $this->eventHandlerMock->shouldReceive('trigger')->never();

        $job = new ExecuteToolJob($toolName, $params, $toolCallId);

        // Expect the job to re-throw the exception
        expect(fn () => $job->handle($this->factoryMock))->toThrow($exception);
    });

    test('job handle method triggers error event and throws if tool not found', function (): void {
        $toolName = 'unknown-tool';
        $params = [];
        $toolCallId = 'test-id-notfound';
        $expectedException = new \RuntimeException("Tool '{$toolName}' not found in registry");

        $this->toolRegistryMock->shouldReceive('hasTool')->once()->with($toolName)->andReturn(false);
        $this->toolRegistryMock->shouldReceive('executeTool')->never();

        // Expect the handle method itself to trigger the error event in this case
        $this->eventHandlerMock->shouldReceive('trigger')
            ->once()
            ->with('tool.error', $toolName, $params, $toolCallId, Mockery::on(function ($e) use ($expectedException) {
                return $e instanceof \RuntimeException && $e->getMessage() === $expectedException->getMessage();
            }));

        $job = new ExecuteToolJob($toolName, $params, $toolCallId);

        // Expect the job to throw the exception
        expect(fn () => $job->handle($this->factoryMock))->toThrow(\RuntimeException::class, $expectedException->getMessage());
    });
});
