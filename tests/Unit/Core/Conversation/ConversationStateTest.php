<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Core\Conversation\ConversationState;

beforeEach(function (): void {
    // Check if Message class exists before trying to use it
    if (! class_exists(Message::class)) {
        var_dump('FATAL: Message class not found in ConversationStateTest::beforeEach');

        throw new \RuntimeException('Message class not found');
    }

    // Explicitly test Message instantiation here
    try {
        $testMsg = new Message(Role::SYSTEM, 'Test');
    } catch (\Throwable $e) {
        // If this fails, dump the error and fail immediately
        var_dump('Error instantiating Message in beforeEach: '.$e->getMessage());

        throw $e;
    }

    $this->model = 'test-model-id';
    $this->conversationOptions = ['temperature' => 0.5];
    $this->state = new ConversationState($this->model, $this->conversationOptions);
    // var_dump('beforeEach completed for ConversationStateTest'); // Can remove debug
});

test('constructor sets model and options correctly', function (): void {
    // var_dump('Running test: constructor sets model...'); // Can remove debug
    // var_dump('State object:', isset($this->state) ? get_class($this->state) : 'null'); // Can remove debug

    // if (!isset($this->state) || !$this->state instanceof ConversationState) { ... } // Can remove debug check

    expect($this->state->getModel())->toBe($this->model);
    // Check against the renamed property
    expect($this->state->getOptions())->toBe($this->conversationOptions);
    expect($this->state->getMessages())->toBeEmpty();
    // var_dump('Checked getModel...'); // Can remove debug
    // var_dump('Checked getOptions...'); // Can remove debug
    // var_dump('Checked getMessages...'); // Can remove debug
});

/* // Restore other tests
test('addMessage adds message to history', function (): void {
    $message = new Message(Role::USER, 'Hello');
    $this->state->addMessage($message);
    expect($this->state->getMessages())->toHaveCount(1)
        ->and($this->state->getMessages()[0])->toBe($message);
});

test('addMessages adds multiple messages', function (): void {
    $messages = [
        new Message(Role::USER, 'Hello'),
        new Message(Role::ASSISTANT, 'Hi there!'),
    ];
    $this->state->addMessages($messages);
    expect($this->state->getMessages())->toHaveCount(2)
        ->and($this->state->getMessages())->toBe($messages);
});

test('addUserMessage adds user message', function (): void {
    $this->state->addUserMessage('Test user message');
    $messages = $this->state->getMessages();
    expect($messages)->toHaveCount(1)
        ->and($messages[0]->role)->toBe(Role::USER)
        ->and($messages[0]->content)->toBe('Test user message');
});

test('addAssistantMessage adds assistant message without tools', function (): void {
    $this->state->addAssistantMessage('Test assistant message');
    $messages = $this->state->getMessages();
    expect($messages)->toHaveCount(1)
        ->and($messages[0]->role)->toBe(Role::ASSISTANT)
        ->and($messages[0]->content)->toBe('Test assistant message')
        ->and($messages[0]->toolCalls)->toBeNull();
});

test('addAssistantMessage adds assistant message with tools', function (): void {
    $toolCall = ToolCall::fromArray(['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'test_func', 'arguments' => '{}']]);
    $this->state->addAssistantMessage('Assistant with tool', [$toolCall]);
    $messages = $this->state->getMessages();
    expect($messages)->toHaveCount(1)
        ->and($messages[0]->role)->toBe(Role::ASSISTANT)
        ->and($messages[0]->content)->toBe('Assistant with tool')
        ->and($messages[0]->toolCalls)->toHaveCount(1)
        ->and($messages[0]->toolCalls[0]->id)->toBe('call_1');
});

test('addToolMessage adds tool result message', function (): void {
    $this->state->addToolMessage('call_123', 'Tool result content');
    $messages = $this->state->getMessages();
    expect($messages)->toHaveCount(1)
        ->and($messages[0]->role)->toBe(Role::TOOL)
        ->and($messages[0]->toolCallId)->toBe('call_123')
        ->and($messages[0]->content)->toBe('Tool result content');
});

test('addSystemMessage adds system message', function (): void {
    $this->state->addSystemMessage('Test system message');
    $messages = $this->state->getMessages();
    expect($messages)->toHaveCount(1)
        ->and($messages[0]->role)->toBe(Role::SYSTEM)
        ->and($messages[0]->content)->toBe('Test system message');
});

test('clearMessages removes all messages', function (): void {
    $this->state->addUserMessage('Message 1');
    $this->state->addAssistantMessage('Message 2');
    expect($this->state->getMessages())->toHaveCount(2);
    $this->state->clearMessages();
    expect($this->state->getMessages())->toBeEmpty();
});

test('getLastMessage returns null when empty', function (): void {
    expect($this->state->getLastMessage())->toBeNull();
});

test('getLastMessage returns the last added message', function (): void {
    $message1 = new Message(Role::USER, 'First');
    $message2 = new Message(Role::ASSISTANT, 'Second');
    $this->state->addMessage($message1);
    $this->state->addMessage($message2);
    expect($this->state->getLastMessage())->toBe($message2);
});

test('setMessages replaces existing messages', function (): void {
    $this->state->addUserMessage('Original message');
    $newMessages = [
        new Message(Role::SYSTEM, 'New system message'),
        new Message(Role::USER, 'New user message'),
    ];
    $this->state->setMessages($newMessages);
    expect($this->state->getMessages())->toHaveCount(2)
        ->and($this->state->getMessages())->toBe($newMessages);
});

test('setMessages filters non-message items', function (): void {
    $this->state->addUserMessage('Original message');
    $newMessages = [
        new Message(Role::SYSTEM, 'Valid message'),
        'invalid string',
        null,
        123,
    ];
    $this->state->setMessages($newMessages);
    expect($this->state->getMessages())->toHaveCount(1)
        ->and($this->state->getMessages()[0]->role)->toBe(Role::SYSTEM);
});
*/
