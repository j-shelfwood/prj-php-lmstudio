<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\ApiClient;
use Shelfwood\LMStudio\Conversation\Conversation;
use Shelfwood\LMStudio\LMStudioFactory;
use Shelfwood\LMStudio\Service\ChatService;
use Shelfwood\LMStudio\Service\CompletionService;
use Shelfwood\LMStudio\Service\EmbeddingService;
use Shelfwood\LMStudio\Service\ModelService;

beforeEach(function (): void {
    $this->factory = new LMStudioFactory('http://example.com/api');
});

test('create api client', function (): void {
    $apiClient = $this->factory->createApiClient();

    expect($apiClient)->toBeInstanceOf(ApiClient::class);
});

test('create model service', function (): void {
    $modelService = $this->factory->createModelService();

    expect($modelService)->toBeInstanceOf(ModelService::class);
});

test('create chat service', function (): void {
    $chatService = $this->factory->createChatService();

    expect($chatService)->toBeInstanceOf(ChatService::class);
});

test('create completion service', function (): void {
    $completionService = $this->factory->createCompletionService();

    expect($completionService)->toBeInstanceOf(CompletionService::class);
});

test('create embedding service', function (): void {
    $embeddingService = $this->factory->createEmbeddingService();

    expect($embeddingService)->toBeInstanceOf(EmbeddingService::class);
});

test('create conversation', function (): void {
    $conversation = $this->factory->createConversation('test-model');

    expect($conversation)->toBeInstanceOf(Conversation::class);
});

test('create conversation with options', function (): void {
    $conversation = $this->factory->createConversation('test-model', ['temperature' => 0.7]);

    expect($conversation)->toBeInstanceOf(Conversation::class);
    expect($conversation->getModel())->toBe('test-model');
    expect($conversation->getOptions())->toBe(['temperature' => 0.7]);
});
