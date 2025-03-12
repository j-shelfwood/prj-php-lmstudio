<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Enum\Role;
use Shelfwood\LMStudio\Enum\ToolType;
use Shelfwood\LMStudio\ValueObject\FunctionCall;
use Shelfwood\LMStudio\ValueObject\Message;
use Shelfwood\LMStudio\ValueObject\ToolCall;

describe('Message', function (): void {
    it('can be instantiated with role and content', function (): void {
        $message = new Message(Role::USER, 'Hello, world!');

        expect($message->role)->toBe(Role::USER);
        expect($message->content)->toBe('Hello, world!');
        expect($message->toolCalls)->toBeNull();
        expect($message->name)->toBeNull();
    });

    it('can be instantiated with a name', function (): void {
        $message = new Message(Role::USER, 'Hello, world!', null, 'John');

        expect($message->role)->toBe(Role::USER);
        expect($message->content)->toBe('Hello, world!');
        expect($message->name)->toBe('John');
    });

    it('can be instantiated with tool calls', function (): void {
        $toolCall = new ToolCall(
            id: 'call_123',
            type: ToolType::FUNCTION,
            function: new FunctionCall(
                name: 'get_weather',
                arguments: '{"location":"San Francisco"}'
            )
        );

        $message = new Message(Role::ASSISTANT, 'Checking weather...', [$toolCall]);

        expect($message->role)->toBe(Role::ASSISTANT);
        expect($message->content)->toBe('Checking weather...');
        expect($message->toolCalls)->toBeArray();
        expect($message->toolCalls[0])->toBeInstanceOf(ToolCall::class);
    });

    it('throws an exception when tool role has no content or tool calls', function (): void {
        expect(fn () => new Message(Role::TOOL))->toThrow(\InvalidArgumentException::class);
    });

    it('can create a system message', function (): void {
        $message = Message::system('System instruction');

        expect($message)->toBeInstanceOf(Message::class);
        expect($message->role)->toBe(Role::SYSTEM);
        expect($message->content)->toBe('System instruction');
    });

    it('can create a user message', function (): void {
        $message = Message::user('User message');

        expect($message)->toBeInstanceOf(Message::class);
        expect($message->role)->toBe(Role::USER);
        expect($message->content)->toBe('User message');
        expect($message->name)->toBeNull();
    });

    it('can create a user message with a name', function (): void {
        $message = Message::user('User message', 'John');

        expect($message)->toBeInstanceOf(Message::class);
        expect($message->role)->toBe(Role::USER);
        expect($message->content)->toBe('User message');
        expect($message->name)->toBe('John');
    });

    it('can create an assistant message', function (): void {
        $message = Message::assistant('Assistant response');

        expect($message)->toBeInstanceOf(Message::class);
        expect($message->role)->toBe(Role::ASSISTANT);
        expect($message->content)->toBe('Assistant response');
        expect($message->toolCalls)->toBeNull();
    });

    it('can create an assistant message with tool calls', function (): void {
        $toolCall = new ToolCall(
            id: 'call_123',
            type: ToolType::FUNCTION,
            function: new FunctionCall(
                name: 'get_weather',
                arguments: '{"location":"San Francisco"}'
            )
        );

        $message = Message::assistant('Assistant response', [$toolCall]);

        expect($message)->toBeInstanceOf(Message::class);
        expect($message->role)->toBe(Role::ASSISTANT);
        expect($message->content)->toBe('Assistant response');
        expect($message->toolCalls)->toBeArray();
        expect($message->toolCalls[0])->toBeInstanceOf(ToolCall::class);
    });

    it('can create a tool message', function (): void {
        $message = Message::tool('Tool response', 'call_123');

        expect($message)->toBeInstanceOf(Message::class);
        expect($message->role)->toBe(Role::TOOL);
        expect($message->content)->toBe('Tool response');
        expect($message->name)->toBe('call_123');
    });

    it('can be serialized to JSON', function (): void {
        $message = new Message(Role::USER, 'Hello, world!', null, 'John');
        $json = $message->jsonSerialize();

        expect($json)->toBeArray();
        expect($json['role'])->toBe('user');
        expect($json['content'])->toBe('Hello, world!');
        expect($json['name'])->toBe('John');
    });

    it('serializes tool calls correctly', function (): void {
        $toolCall = new ToolCall(
            id: 'call_123',
            type: ToolType::FUNCTION,
            function: new FunctionCall(
                name: 'get_weather',
                arguments: '{"location":"San Francisco"}'
            )
        );

        $message = new Message(Role::ASSISTANT, 'Checking weather...', [$toolCall]);
        $json = $message->jsonSerialize();

        expect($json)->toBeArray();
        expect($json['role'])->toBe('assistant');
        expect($json['content'])->toBe('Checking weather...');
        expect($json['tool_calls'])->toBeArray();
        expect($json['tool_calls'][0]['id'])->toBe('call_123');
        expect($json['tool_calls'][0]['type'])->toBe('function');
        expect($json['tool_calls'][0]['function']['name'])->toBe('get_weather');
    });

    it('serializes tool message correctly', function (): void {
        $message = Message::tool('Tool response', 'call_123');
        $json = $message->jsonSerialize();

        expect($json)->toBeArray();
        expect($json['role'])->toBe('tool');
        expect($json['content'])->toBe('Tool response');
        expect($json['tool_call_id'])->toBe('call_123');
        expect(isset($json['name']))->toBeFalse();
    });
});
