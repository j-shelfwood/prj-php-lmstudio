<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Queue\Queue as QueueDispatcherContract;
use Psr\Log\LoggerInterface;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Manager\ConversationManager;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\Laravel\Conversation\QueueableConversationBuilder;
use Shelfwood\LMStudio\Laravel\Tools\QueueableToolExecutionHandler;
use Shelfwood\LMStudio\LMStudioFactory;

describe('QueueableConversationBuilder', function (): void {

    beforeEach(function (): void {
        /** @var LMStudioFactory&Mockery\MockInterface */
        $this->factoryMock = Mockery::mock(LMStudioFactory::class)->makePartial();
        $this->toolRegistryMock = Mockery::mock(ToolRegistry::class);
        $this->eventHandlerMock = Mockery::mock(EventHandler::class)->makePartial(); // Allow `on`
        $this->toolExecutorMock = Mockery::mock(ToolExecutor::class);
        $this->queueDispatcherMock = Mockery::mock(QueueDispatcherContract::class);
        $this->configMock = Mockery::mock(ConfigContract::class);
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->chatServiceMock = Mockery::mock(ChatService::class);
        $this->streamingHandlerMock = Mockery::mock(StreamingHandler::class);

        // Mock app() helper resolution
        $this->app->instance(QueueDispatcherContract::class, $this->queueDispatcherMock);
        $this->app->instance(ConfigContract::class, $this->configMock);

        // Configure factory mock return values
        $this->factoryMock->shouldReceive('getToolRegistry')->andReturn($this->toolRegistryMock);
        $this->factoryMock->shouldReceive('getEventHandler')->andReturn($this->eventHandlerMock);
        $this->factoryMock->shouldReceive('createToolExecutor')->andReturn($this->toolExecutorMock);
        $this->factoryMock->shouldReceive('getLogger')->andReturn($this->loggerMock);
        $this->factoryMock->shouldReceive('getChatService')->andReturn($this->chatServiceMock);
        $this->factoryMock->shouldReceive('createStreamingHandler')->andReturn($this->streamingHandlerMock);

        // Default config
        $this->configMock->shouldReceive('get')->with('lmstudio.queue.tools_by_default', false)->andReturn(false);

        // Create the builder instance using the mocked factory
        $this->builder = new QueueableConversationBuilder(
            $this->factoryMock,
            'test-model'
        );
    });

    test('setToolQueueable stores configuration', function (): void {
        $this->builder->setToolQueueable('tool1', true);
        $this->builder->setToolQueueable('tool2', false);

        $reflection = new \ReflectionClass($this->builder);
        $configProperty = $reflection->getProperty('queueableToolsConfig');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($this->builder);

        expect($config)->toBe(['tool1' => true, 'tool2' => false]);
    });

    test('withQueueableTool registers tool and sets queueable=true', function (): void {
        $toolName = 'my_tool_true';
        $callback = fn () => 'result_true';
        $params = ['type' => 'object'];

        $this->toolRegistryMock->shouldReceive('registerTool')->once()->with($toolName, $callback, $params, null);

        $this->builder->withQueueableTool($toolName, $callback, $params, null, true);

        $reflection = new \ReflectionClass($this->builder);
        $configProperty = $reflection->getProperty('queueableToolsConfig');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($this->builder);
        expect($config[$toolName])->toBeTrue();
    });

    test('withQueueableTool registers tool and sets queueable=false', function (): void {
        $toolName = 'my_tool_false';
        $callback = fn () => 'result_false';
        $params = ['type' => 'string'];

        $this->toolRegistryMock->shouldReceive('registerTool')->once()->with($toolName, $callback, $params, null);

        $this->builder->withQueueableTool($toolName, $callback, $params, null, false);

        $reflection = new \ReflectionClass($this->builder);
        $configProperty = $reflection->getProperty('queueableToolsConfig');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($this->builder);
        expect($config[$toolName])->toBeFalse();
    });

    test('withQueueableTool registers tool and sets queueable=null (uses default)', function (): void {
        $toolName = 'my_tool_null';
        $callback = fn () => 'result_null';
        $params = ['type' => 'boolean'];

        // Assume default is false based on beforeEach setup
        $this->configMock->shouldReceive('get')->with('lmstudio.queue.tools_by_default', false)->andReturn(false);

        $this->toolRegistryMock->shouldReceive('registerTool')->once()->with($toolName, $callback, $params, null);

        $this->builder->withQueueableTool($toolName, $callback, $params, null, null);

        $reflection = new \ReflectionClass($this->builder);
        $configProperty = $reflection->getProperty('queueableToolsConfig');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($this->builder);
        expect($config[$toolName])->toBeFalse(); // Expecting default (false)
    });

    // TODO: Test `onToolQueued` once event handling is finalized.

    test('build method creates manager with QueueableToolExecutionHandler', function (): void {
        // Configure specific tool queueing - MOVED BELOW
        // $this->builder->setToolQueueable('queued_tool', true);

        // Mock the executor that the builder *sets* via withToolExecutor
        $capturedExecutor = null;
        $this->builder = Mockery::spy(QueueableConversationBuilder::class, [$this->factoryMock, 'test-model'])->makePartial();
        $this->builder->shouldReceive('withToolExecutor')->once()->with(Mockery::capture($capturedExecutor));

        // Configure specific tool queueing ON THE SPY
        $this->builder->setToolQueueable('queued_tool', true);

        // Build the manager
        $manager = $this->builder->build();

        expect($manager)->toBeInstanceOf(ConversationManager::class);
        expect($capturedExecutor)->not->toBeNull();
        expect($capturedExecutor)->toBeInstanceOf(QueueableToolExecutionHandler::class);

        // Verify the captured executor has the correct configuration
        expect($capturedExecutor->shouldQueueTool('queued_tool'))->toBeTrue();
        expect($capturedExecutor->shouldQueueTool('default_tool'))->toBeFalse(); // Uses default
    });
});
