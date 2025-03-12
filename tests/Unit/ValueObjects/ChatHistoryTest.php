<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Enum\Role;
use Shelfwood\LMStudio\Enum\ToolType;
use Shelfwood\LMStudio\ValueObject\ChatHistory;
use Shelfwood\LMStudio\ValueObject\FunctionCall;
use Shelfwood\LMStudio\ValueObject\Message;
use Shelfwood\LMStudio\ValueObject\ToolCall;

describe('ChatHistory', function (): void {
    it('can be instantiated with no messages', function (): void {
        $history = new ChatHistory;

        expect($history->count())->toBe(0);
        expect($history->getMessages())->toBeArray();
        expect($history->getMessages())->toBeEmpty();
    });

    it('can be instantiated with messages', function (): void {
        $messages = [
            Message::system('System message'),
            Message::user('User message'),
        ];

        $history = new ChatHistory($messages);

        expect($history->count())->toBe(2);
        expect($history->getMessages())->toHaveCount(2);
        expect($history->getMessages()[0]->role)->toBe(Role::SYSTEM);
        expect($history->getMessages()[1]->role)->toBe(Role::USER);
    });

    it('can add a message', function (): void {
        $history = new ChatHistory;
        $message = Message::user('Hello');

        $history->addMessage($message);

        expect($history->count())->toBe(1);
        expect($history->getMessages()[0])->toBe($message);
    });

    it('can add multiple messages', function (): void {
        $history = new ChatHistory;
        $message1 = Message::user('Hello');
        $message2 = Message::assistant('Hi there');

        $history->addMessage($message1);
        $history->addMessage($message2);

        expect($history->count())->toBe(2);
        expect($history->getMessages()[0])->toBe($message1);
        expect($history->getMessages()[1])->toBe($message2);
    });

    it('can add a system message', function (): void {
        $history = new ChatHistory;

        $history->addSystemMessage('System instruction');

        expect($history->count())->toBe(1);
        expect($history->getMessages()[0]->role)->toBe(Role::SYSTEM);
        expect($history->getMessages()[0]->content)->toBe('System instruction');
    });

    it('can add a user message', function (): void {
        $history = new ChatHistory;

        $history->addUserMessage('User message');

        expect($history->count())->toBe(1);
        expect($history->getMessages()[0]->role)->toBe(Role::USER);
        expect($history->getMessages()[0]->content)->toBe('User message');
    });

    it('can add a user message with a name', function (): void {
        $history = new ChatHistory;

        $history->addUserMessage('User message', 'John');

        expect($history->count())->toBe(1);
        expect($history->getMessages()[0]->role)->toBe(Role::USER);
        expect($history->getMessages()[0]->content)->toBe('User message');
        expect($history->getMessages()[0]->name)->toBe('John');
    });

    it('can add an assistant message', function (): void {
        $history = new ChatHistory;

        $history->addAssistantMessage('Assistant response');

        expect($history->count())->toBe(1);
        expect($history->getMessages()[0]->role)->toBe(Role::ASSISTANT);
        expect($history->getMessages()[0]->content)->toBe('Assistant response');
    });

    it('can add an assistant message with tool calls', function (): void {
        $history = new ChatHistory;
        $toolCall = new ToolCall(
            id: 'call_123',
            type: ToolType::FUNCTION,
            function: new FunctionCall(
                name: 'get_weather',
                arguments: '{"location":"San Francisco"}'
            )
        );

        $history->addAssistantMessage('Assistant response', [$toolCall]);

        expect($history->count())->toBe(1);
        expect($history->getMessages()[0]->role)->toBe(Role::ASSISTANT);
        expect($history->getMessages()[0]->content)->toBe('Assistant response');
        expect($history->getMessages()[0]->toolCalls)->toBeArray();
        expect($history->getMessages()[0]->toolCalls[0])->toBeInstanceOf(ToolCall::class);
    });

    it('can add a tool message', function (): void {
        $history = new ChatHistory;

        $history->addToolMessage('Tool response', 'call_123');

        expect($history->count())->toBe(1);
        expect($history->getMessages()[0]->role)->toBe(Role::TOOL);
        expect($history->getMessages()[0]->content)->toBe('Tool response');
        expect($history->getMessages()[0]->name)->toBe('call_123');
    });

    it('can clear the history', function (): void {
        $history = new ChatHistory([
            Message::system('System message'),
            Message::user('User message'),
        ]);

        expect($history->count())->toBe(2);

        $history->clear();

        expect($history->count())->toBe(0);
        expect($history->getMessages())->toBeEmpty();
    });

    it('can be counted', function (): void {
        $history = new ChatHistory([
            Message::system('System message'),
            Message::user('User message'),
            Message::assistant('Assistant response'),
        ]);

        expect(count($history))->toBe(3);
    });

    it('can be iterated', function (): void {
        $messages = [
            Message::system('System message'),
            Message::user('User message'),
            Message::assistant('Assistant response'),
        ];

        $history = new ChatHistory($messages);

        $i = 0;

        foreach ($history as $message) {
            expect($message)->toBe($messages[$i]);
            $i++;
        }

        expect($i)->toBe(3);
    });

    it('can be serialized to JSON', function (): void {
        $messages = [
            Message::system('System message'),
            Message::user('User message'),
            Message::assistant('Assistant response'),
        ];

        $history = new ChatHistory($messages);
        $json = $history->jsonSerialize();

        expect($json)->toBeArray();
        expect($json)->toHaveCount(3);
        expect($json[0]['role'])->toBe('system');
        expect($json[1]['role'])->toBe('user');
        expect($json[2]['role'])->toBe('assistant');
    });
});
