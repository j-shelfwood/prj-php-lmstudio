<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Queue as QueueDispatcherContract;
use Mockery as m;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\Laravel\Conversation\QueueableConversationBuilder;
use Shelfwood\LMStudio\Laravel\Tools\QueueableToolExecutionHandler;

describe('QueueableConversationBuilder', function (): void {
    beforeEach(function (): void {
        $this->chatServiceMock = m::mock(ChatService::class);
        $this->toolRegistryMock = m::mock(ToolRegistry::class);
        $this->eventHandlerMock = m::mock(EventHandler::class);
        $this->queueDispatcherMock = m::mock(QueueDispatcherContract::class);

        // Create the builder, injecting core mocks
        $this->builder = new QueueableConversationBuilder(
            $this->chatServiceMock,
            'test-model',
            $this->toolRegistryMock,
            $this->eventHandlerMock,
            $this->queueDispatcherMock
        );

        // Remove general allowances
        // $this->toolRegistryMock->allows('registerTool');
        // $this->eventHandlerMock->allows('on');
    });

    afterEach(function (): void {
        m::close();
    });

    test('constructor initializes correctly', function (): void {
        // Test the constructor properly creates the internal handler
        $reflection = new \ReflectionClass($this->builder);
        $parentReflection = $reflection->getParentClass();
        $executorProperty = $parentReflection->getProperty('toolExecutor');
        $executorProperty->setAccessible(true);
        $executor = $executorProperty->getValue($this->builder);
        expect($executor)->toBeInstanceOf(QueueableToolExecutionHandler::class);
    });

    test('on tool queued triggers event handler', function (): void {
        $callback = function (): void {};

        // Expect the event handler's `on` method to be called via the handler's `onQueued`
        $this->eventHandlerMock->shouldReceive('on')
            ->once()
            ->with('lmstudio.tool.queued', $callback);

        $result = $this->builder->onToolQueued($callback);
        expect($result)->toBe($this->builder); // Still check for chaining
    });

    test('with queueable tool registers tool', function (): void {
        $toolName = 'get_weather';
        $parameters = ['type' => 'object'];
        $executor = fn ($args) => ['temp' => 22];

        // Expect ToolRegistry::registerTool to be called via parent::withTool
        $this->toolRegistryMock->shouldReceive('registerTool')
            ->once()
            ->with($toolName, $executor, $parameters, null);

        // We are not testing the handler delegation directly anymore
        // $this->mockQueueableHandler->shouldReceive('setToolQueueable') ...

        $result = $this->builder->withQueueableTool($toolName, $executor, $parameters);
        expect($result)->toBe($this->builder); // Still check for chaining
    });
});
