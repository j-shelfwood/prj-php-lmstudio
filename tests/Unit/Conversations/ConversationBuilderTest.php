<?php

declare(strict_types=1);

namespace Tests\Unit\Conversations;

use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Conversations\Conversation;
use Shelfwood\LMStudio\Conversations\ConversationBuilder;
use Shelfwood\LMStudio\Enums\Role;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;

// Helper function to create a properly mocked client
function createMockedClient()
{
    $config = new LMStudioConfig;

    $client = test()->createMock(LMStudioClientInterface::class);
    $client->method('getConfig')->willReturn($config);

    return $client;
}

it('can be instantiated', function (): void {
    $client = createMockedClient();
    $builder = new ConversationBuilder($client);

    expect($builder)->toBeInstanceOf(ConversationBuilder::class);
});

it('can build a conversation with default values', function (): void {
    $client = createMockedClient();

    $builder = new ConversationBuilder($client);
    $conversation = $builder->build();

    expect($conversation)->toBeInstanceOf(Conversation::class);
    expect($conversation->getTitle())->toBe('New Conversation');
});

it('can set a custom title', function (): void {
    $client = createMockedClient();

    $builder = new ConversationBuilder($client);
    $conversation = $builder->withTitle('Custom Title')->build();

    expect($conversation->getTitle())->toBe('Custom Title');
});

it('can set a custom ID', function (): void {
    $client = createMockedClient();

    $builder = new ConversationBuilder($client);
    $conversation = $builder->withId('custom_id')->build();

    expect($conversation->getId())->toBe('custom_id');
});

it('can set an existing chat history', function (): void {
    $client = createMockedClient();

    $history = new ChatHistory;
    $history->addSystemMessage('System message');

    $builder = new ConversationBuilder($client);
    $conversation = $builder->withHistory($history)->build();

    expect($conversation->getHistory())->toBe($history);
    expect($conversation->getMessages())->toHaveCount(1);
});

it('can set a tool registry', function (): void {
    $client = createMockedClient();

    $toolRegistry = new ToolRegistry;

    $builder = new ConversationBuilder($client);
    $conversation = $builder->withToolRegistry($toolRegistry)->build();

    expect($conversation->getToolRegistry())->toBe($toolRegistry);
});

it('can set model parameters', function (): void {
    $client = createMockedClient();

    $builder = new ConversationBuilder($client);
    $conversation = $builder
        ->withModel('qwen2.5-7b-instruct-1m')
        ->withTemperature(0.5)
        ->withMaxTokens(2000)
        ->build();

    expect($conversation->getModel())->toBe('qwen2.5-7b-instruct-1m');
    expect($conversation->getTemperature())->toBe(0.5);
    expect($conversation->getMaxTokens())->toBe(2000);
});

it('can set metadata', function (): void {
    $client = createMockedClient();

    $builder = new ConversationBuilder($client);
    $conversation = $builder
        ->withMetadata('key1', 'value1')
        ->withMetadata(['key2' => 'value2', 'key3' => 'value3'])
        ->build();

    expect($conversation->getMetadata('key1'))->toBe('value1');
    expect($conversation->getMetadata('key2'))->toBe('value2');
    expect($conversation->getMetadata('key3'))->toBe('value3');
    expect($conversation->getAllMetadata())->toHaveCount(3);
});

it('can add system messages', function (): void {
    $client = createMockedClient();

    $builder = new ConversationBuilder($client);
    $conversation = $builder
        ->withSystemMessage('System message 1')
        ->withSystemMessage('System message 2')
        ->build();

    $messages = $conversation->getMessages();
    expect($messages)->toHaveCount(2);
    expect($messages[0]->role)->toBe(Role::SYSTEM);
    expect($messages[0]->content)->toBe('System message 1');
    expect($messages[1]->role)->toBe(Role::SYSTEM);
    expect($messages[1]->content)->toBe('System message 2');
});

it('can add user messages', function (): void {
    $client = createMockedClient();

    $builder = new ConversationBuilder($client);
    $conversation = $builder
        ->withUserMessage('User message 1')
        ->withUserMessage('User message 2', 'John')
        ->build();

    $messages = $conversation->getMessages();
    expect($messages)->toHaveCount(2);
    expect($messages[0]->role)->toBe(Role::USER);
    expect($messages[0]->content)->toBe('User message 1');
    expect($messages[0]->name)->toBeNull();
    expect($messages[1]->role)->toBe(Role::USER);
    expect($messages[1]->content)->toBe('User message 2');
    expect($messages[1]->name)->toBe('John');
});

it('can be created using the static factory method', function (): void {
    $client = createMockedClient();

    $builder = Conversation::builder($client);

    expect($builder)->toBeInstanceOf(ConversationBuilder::class);

    $conversation = $builder->build();
    expect($conversation)->toBeInstanceOf(Conversation::class);
});
