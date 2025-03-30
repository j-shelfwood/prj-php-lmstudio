<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\Laravel\Jobs\ExecuteToolJob;

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
            60,
            'test-queue',
            'test-connection'
        );

        // Use reflection to access protected properties
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
        expect($job->tries)->toBe(5); // Public readonly
        expect($job->timeout)->toBe(60); // Public readonly
    });

    test('job constructor sets default values correctly', function (): void {
        // Set up config values for the test
        config(['lmstudio.queue.tries' => 3]);
        config(['lmstudio.queue.timeout' => 60]);
        config(['lmstudio.queue.queue' => 'default']);
        config(['lmstudio.queue.connection' => null]);

        $job = new ExecuteToolJob('test-tool', [], 'test-id');

        expect($job->tries)->toBe(3);
        expect($job->timeout)->toBe(60);
        expect($job->queue)->toBe('default');
        expect($job->connection)->toBeNull();
    });

    test('job handle method executes tool correctly', function (): void {
        // Create a mock tool registry
        $toolRegistry = Mockery::mock(ToolRegistry::class);

        // Set up expectations
        $toolRegistry->shouldReceive('hasTool')
            ->once()
            ->with('test-tool')
            ->andReturn(true);

        $toolRegistry->shouldReceive('executeTool')
            ->once()
            ->with('test-tool', ['arg' => 'value'])
            ->andReturn(['result' => 'success']);

        // Create a success callback
        $successCalled = false;
        $successCallback = function ($result, $toolCallId) use (&$successCalled): void {
            $successCalled = true;
            expect($result)->toBe(['result' => 'success']);
            expect($toolCallId)->toBe('test-id');
        };

        // Listen for the success event
        Event::listen('lmstudio.tool.success', $successCallback);

        // Create the job
        $job = new ExecuteToolJob(
            'test-tool',
            ['arg' => 'value'],
            'test-id'
        );

        // Execute the job
        $job->handle($toolRegistry);

        // Assert the success callback was called
        expect($successCalled)->toBeTrue();
    });

    test('job handle method calls error callback on exception', function (): void {
        // Create a mock tool registry
        $toolRegistry = Mockery::mock(ToolRegistry::class);

        // Set up expectations
        $toolRegistry->shouldReceive('hasTool')
            ->once()
            ->with('test-tool')
            ->andReturn(true);

        $toolRegistry->shouldReceive('executeTool')
            ->once()
            ->with('test-tool', ['arg' => 'value'])
            ->andThrow(new \Exception('Test error'));

        // Create an error callback
        $errorCalled = false;
        $errorCallback = function ($error, $toolCallId) use (&$errorCalled): void {
            $errorCalled = true;
            expect($error)->toBeInstanceOf(\Exception::class);
            expect($error->getMessage())->toBe('Test error');
            expect($toolCallId)->toBe('test-id');
        };

        // Listen for the error event
        Event::listen('lmstudio.tool.error', $errorCallback);

        // Create the job
        $job = new ExecuteToolJob(
            'test-tool',
            ['arg' => 'value'],
            'test-id'
        );

        // Execute the job and expect an exception
        expect(fn () => $job->handle($toolRegistry))->toThrow(\Exception::class, 'Test error');

        // Assert the error callback was called
        expect($errorCalled)->toBeTrue();
    });

    test('job handle method throws exception when tool not found', function (): void {
        // Create a mock tool registry
        $toolRegistry = Mockery::mock(ToolRegistry::class);

        // Set up expectations
        $toolRegistry->shouldReceive('hasTool')
            ->once()
            ->with('test-tool')
            ->andReturn(false);

        // Create the job
        $job = new ExecuteToolJob('test-tool', [], 'test-id');

        // Execute the job and expect an exception
        expect(fn () => $job->handle($toolRegistry))
            ->toThrow(\RuntimeException::class, "Tool 'test-tool' not found in registry");
    });
});
