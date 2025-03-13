<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Builder\ConversationBuilder;
use Shelfwood\LMStudio\Core\Conversation\Conversation;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Laravel\Conversation\QueueableConversationBuilder;
use Shelfwood\LMStudio\Laravel\Streaming\LaravelStreamingHandler;
use Shelfwood\LMStudio\LMStudioFactory;

describe('LMStudioFactoryLaravel', function (): void {
    beforeEach(function (): void {
        // Create a mock chat service
        $chatService = Mockery::mock(ChatService::class);

        // Create a factory with mocked dependencies
        $this->factory = Mockery::mock(LMStudioFactory::class, ['http://example.com/api', [], 'test-api-key'])->makePartial();
        $this->factory->shouldReceive('getChatService')->andReturn($chatService);
    });

    test('factory creates streaming handler', function (): void {
        $factory = new LMStudioFactory('http://example.com/api', [], 'test-api-key');
        $handler = $factory->createStreamingHandler();

        expect($handler)->toBeInstanceOf(StreamingHandler::class);
    });

    test('factory creates streaming conversation', function (): void {
        // Create a real factory
        $factory = new LMStudioFactory('http://example.com/api', [], 'test-api-key');

        // Create a streaming conversation
        $conversation = $factory->createConversation('test-model', ['stream' => true]);

        // Assert the conversation has streaming enabled
        expect($conversation)->toBeInstanceOf(Conversation::class);
        expect($conversation->getOptions())->toHaveKey('stream');
        expect($conversation->getOptions()['stream'])->toBeTrue();
    });

    test('factory creates streaming conversation builder', function (): void {
        // Create a real factory
        $factory = new LMStudioFactory('http://example.com/api', [], 'test-api-key');

        // Create a conversation builder with streaming
        $builder = $factory->createConversationBuilder('test-model');
        $builder = $builder->withStreaming();

        // Assert the builder has streaming enabled
        expect($builder)->toBeInstanceOf(ConversationBuilder::class);

        // Build a conversation and check if streaming is enabled
        $conversation = $builder->build();
        expect($conversation->getOptions())->toHaveKey('stream');
        expect($conversation->getOptions()['stream'])->toBeTrue();
    });

    test('factory creates Laravel streaming handler when class exists', function (): void {
        // Skip this test if the LaravelStreamingHandler class doesn't exist
        if (! class_exists(LaravelStreamingHandler::class)) {
            $this->markTestSkipped('LaravelStreamingHandler class does not exist');
        }

        // Create a real factory
        $factory = new LMStudioFactory('http://example.com/api', [], 'test-api-key');

        // Test that the factory can create a LaravelStreamingHandler
        $handler = $factory->createLaravelStreamingHandler();
        expect($handler)->toBeInstanceOf(LaravelStreamingHandler::class);
    });

    test('factory creates queueable conversation builder when class exists', function (): void {
        // Skip this test if the QueueableConversationBuilder class doesn't exist
        if (! class_exists(QueueableConversationBuilder::class)) {
            $this->markTestSkipped('QueueableConversationBuilder class does not exist');
        }

        // Create a real factory
        $factory = new LMStudioFactory('http://example.com/api', [], 'test-api-key');

        // Test that the factory can create a QueueableConversationBuilder
        $builder = $factory->createQueueableConversationBuilder('test-model');
        expect($builder)->toBeInstanceOf(QueueableConversationBuilder::class);
    });
});
