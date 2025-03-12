<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\ApiClient;
use Shelfwood\LMStudio\Conversation\Conversation;
use Shelfwood\LMStudio\Enum\Role;
use Shelfwood\LMStudio\LMStudioFactory;
use Shelfwood\LMStudio\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Service\ChatService;
use Shelfwood\LMStudio\Service\CompletionService;
use Shelfwood\LMStudio\Service\EmbeddingService;
use Shelfwood\LMStudio\Service\ModelService;

test('factory creates services that work together', function (): void {
    // Create a mock factory that will return mocked services
    $factory = Mockery::mock(LMStudioFactory::class, ['http://example.com/api'])->makePartial();

    // Create a mock API client
    $apiClient = Mockery::mock(ApiClient::class);
    $factory->shouldReceive('createApiClient')->andReturn($apiClient);

    // Create a mock chat service
    $chatService = Mockery::mock(ChatService::class);
    $factory->shouldReceive('createChatService')->andReturn($chatService);

    // Load the mock response
    $mockResponse = json_decode(file_get_contents(__DIR__.'/../mocks/chat/standard-response.json'), true);
    $chatCompletionResponse = ChatCompletionResponse::fromArray($mockResponse);

    // Set up the chat service mock to return the mock response
    $chatService->shouldReceive('createCompletion')
        ->once()
        ->with('qwen2.5-7b-instruct-1m', Mockery::type('array'), [])
        ->andReturn($chatCompletionResponse);

    // Create a mock conversation
    $conversation = Mockery::mock(Conversation::class, [$chatService, 'qwen2.5-7b-instruct-1m'])->makePartial();
    $factory->shouldReceive('createConversation')
        ->with('qwen2.5-7b-instruct-1m')
        ->andReturn($conversation);

    // Create a conversation
    $conversation = $factory->createConversation('qwen2.5-7b-instruct-1m');

    // Add messages to the conversation
    $conversation->addSystemMessage('You are a helpful assistant.');
    $conversation->addUserMessage('What\'s the weather like in London?');

    // Get a response
    $response = $conversation->getResponse();

    // Assert the response is correct
    $expectedContent = "I'm sorry for any inconvenience, but as an AI, I don't have real-time capabilities to provide current weather updates or forecasts. Please check a reliable weather website or app for the most accurate information on the weather in London.";
    expect($response)->toBe($expectedContent);

    // Assert the conversation history is maintained
    $messages = $conversation->getMessages();
    expect($messages)->toHaveCount(3);
    expect($messages[0]->getRole())->toBe(Role::SYSTEM);
    expect($messages[0]->getContent())->toBe('You are a helpful assistant.');
    expect($messages[1]->getRole())->toBe(Role::USER);
    expect($messages[1]->getContent())->toBe('What\'s the weather like in London?');
    expect($messages[2]->getRole())->toBe(Role::ASSISTANT);
    expect($messages[2]->getContent())->toBe($expectedContent);
});

test('factory creates services with correct dependencies', function (): void {
    $factory = new LMStudioFactory('http://example.com/api');

    // Create the services
    $apiClient = $factory->createApiClient();
    $modelService = $factory->createModelService();
    $chatService = $factory->createChatService();
    $completionService = $factory->createCompletionService();
    $embeddingService = $factory->createEmbeddingService();

    // Assert the services are of the correct type
    expect($apiClient)->toBeInstanceOf(ApiClient::class);
    expect($modelService)->toBeInstanceOf(ModelService::class);
    expect($chatService)->toBeInstanceOf(ChatService::class);
    expect($completionService)->toBeInstanceOf(CompletionService::class);
    expect($embeddingService)->toBeInstanceOf(EmbeddingService::class);

    // Create a conversation with options
    $conversation = $factory->createConversation('test-model', ['temperature' => 0.7]);

    // Assert the conversation is of the correct type and has the correct options
    expect($conversation)->toBeInstanceOf(Conversation::class);
    expect($conversation->getModel())->toBe('test-model');
    expect($conversation->getOptions())->toBe(['temperature' => 0.7]);
});
