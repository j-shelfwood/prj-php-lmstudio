<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use Mockery;
use Shelfwood\LMStudio\Conversation\Conversation;
use Shelfwood\LMStudio\Enum\FinishReason;
use Shelfwood\LMStudio\Enum\Role;
use Shelfwood\LMStudio\Model\Choice;
use Shelfwood\LMStudio\Model\Message;
use Shelfwood\LMStudio\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Service\ChatService;

beforeEach(function (): void {
    $this->chatService = Mockery::mock(ChatService::class);
    $this->conversation = new Conversation($this->chatService, 'test-model');
});

afterEach(function (): void {
    Mockery::close();
});

it('add system message', function (): void {
    $this->conversation->addSystemMessage('Hello');
    $messages = $this->conversation->getMessages();

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toBeInstanceOf(Message::class);
    expect($messages[0]->getRole())->toBe(Role::SYSTEM);
    expect($messages[0]->getContent())->toBe('Hello');
});

it('add user message', function (): void {
    $this->conversation->addUserMessage('Hello');
    $messages = $this->conversation->getMessages();

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toBeInstanceOf(Message::class);
    expect($messages[0]->getRole())->toBe(Role::USER);
    expect($messages[0]->getContent())->toBe('Hello');
});

it('add assistant message', function (): void {
    $this->conversation->addAssistantMessage('Hello');
    $messages = $this->conversation->getMessages();

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toBeInstanceOf(Message::class);
    expect($messages[0]->getRole())->toBe(Role::ASSISTANT);
    expect($messages[0]->getContent())->toBe('Hello');
});

it('add tool message', function (): void {
    $this->conversation->addToolMessage('Hello', 'tool-123');
    $messages = $this->conversation->getMessages();

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toBeInstanceOf(Message::class);
    expect($messages[0]->getRole())->toBe(Role::TOOL);
    expect($messages[0]->getContent())->toBe('Hello');
    expect($messages[0]->getToolCallId())->toBe('tool-123');
});

it('get response', function (): void {
    $this->conversation->addUserMessage('Hello');

    // Create a mock Choice object
    $choice = new Choice(
        index: 0,
        logprobs: null,
        finishReason: FinishReason::STOP,
        message: [
            'role' => 'assistant',
            'content' => 'Hi there!',
        ]
    );

    // Create a mock ChatCompletionResponse
    $response = Mockery::mock(ChatCompletionResponse::class);
    $response->shouldReceive('getChoices')->andReturn([$choice]);

    // Set up the chat service mock
    $this->chatService
        ->shouldReceive('createCompletion')
        ->with('test-model', Mockery::type('array'), [])
        ->andReturn($response);

    $result = $this->conversation->getResponse();

    expect($result)->toBe('Hi there!');
    expect($this->conversation->getMessages())->toHaveCount(2);
    expect($this->conversation->getMessages()[1]->getRole())->toBe(Role::ASSISTANT);
    expect($this->conversation->getMessages()[1]->getContent())->toBe('Hi there!');
});

it('clear messages', function (): void {
    $this->conversation->addUserMessage('Hello');
    expect($this->conversation->getMessages())->toHaveCount(1);

    $this->conversation->clearMessages();
    expect($this->conversation->getMessages())->toHaveCount(0);
});

it('set model', function (): void {
    $this->conversation->setModel('new-model');
    expect($this->conversation->getModel())->toBe('new-model');
});

it('set options', function (): void {
    $options = ['temperature' => 0.7];
    $this->conversation->setOptions($options);
    expect($this->conversation->getOptions())->toBe($options);
});
