<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Enum\Role;
use Shelfwood\LMStudio\Http\Response\Common\Function_;
use Shelfwood\LMStudio\Http\Response\Common\Message;
use Shelfwood\LMStudio\Http\Response\Common\ToolCall;

describe('Message', function (): void {
    it('can be instantiated with role and content', function (): void {
        $message = new Message(
            role: Role::USER,
            content: 'Hello, world!'
        );

        expect($message->role)->toBe(Role::USER);
        expect($message->content)->toBe('Hello, world!');
        expect($message->toolCalls)->toBeNull();
    });

    it('can be instantiated with tool calls', function (): void {
        $function = new Function_(
            name: 'get_weather',
            arguments: '{"location":"San Francisco"}'
        );

        $toolCall = new ToolCall(
            id: 'call_123',
            type: 'function',
            function: $function
        );

        $message = new Message(
            role: Role::ASSISTANT,
            content: 'Checking weather...',
            toolCalls: [$toolCall]
        );

        expect($message->role)->toBe(Role::ASSISTANT);
        expect($message->content)->toBe('Checking weather...');
        expect($message->toolCalls)->toBeArray();
        expect($message->toolCalls[0])->toBeInstanceOf(ToolCall::class);
    });

    it('can be created from an array with content', function (): void {
        $data = [
            'role' => 'user',
            'content' => 'Hello, world!',
        ];

        $message = Message::fromArray($data);

        expect($message->role)->toBe(Role::USER);
        expect($message->content)->toBe('Hello, world!');
        expect($message->toolCalls)->toBeNull();
    });

    it('can be created from an array with tool calls', function (): void {
        $data = [
            'role' => 'assistant',
            'content' => 'Checking weather...',
            'tool_calls' => [
                [
                    'id' => 'call_123',
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_weather',
                        'arguments' => '{"location":"San Francisco"}',
                    ],
                ],
            ],
        ];

        $message = Message::fromArray($data);

        expect($message->role)->toBe(Role::ASSISTANT);
        expect($message->content)->toBe('Checking weather...');
        expect($message->toolCalls)->toBeArray();
        expect($message->toolCalls[0])->toBeInstanceOf(ToolCall::class);
        expect($message->toolCalls[0]->id)->toBe('call_123');
        expect($message->toolCalls[0]->function->name)->toBe('get_weather');
    });

    it('handles missing data when created from array', function (): void {
        $message = Message::fromArray([]);

        expect($message->role)->toBe(Role::ASSISTANT);
        expect($message->content)->toBeNull();
        expect($message->toolCalls)->toBeNull();
    });

    it('handles non-array tool_calls when created from array', function (): void {
        $data = [
            'role' => 'assistant',
            'content' => 'Hello',
            'tool_calls' => 'not an array',
        ];

        $message = Message::fromArray($data);

        expect($message->role)->toBe(Role::ASSISTANT);
        expect($message->content)->toBe('Hello');
        expect($message->toolCalls)->toBeNull();
    });
});
