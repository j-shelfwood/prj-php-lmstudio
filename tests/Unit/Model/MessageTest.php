<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Model\Message;

test('message can be created with role and content', function (): void {
    $message = new Message(Role::USER, 'Hello, world!');

    expect($message)->toBeInstanceOf(Message::class);
    expect($message->getRole())->toBe(Role::USER);
    expect($message->getContent())->toBe('Hello, world!');
    expect($message->getToolCallId())->toBeNull();
});

test('message can be created with role, content, and tool call id', function (): void {
    $message = new Message(Role::TOOL, 'The weather is sunny.', null, 'call_123');

    expect($message)->toBeInstanceOf(Message::class);
    expect($message->getRole())->toBe(Role::TOOL);
    expect($message->getContent())->toBe('The weather is sunny.');
    expect($message->getToolCallId())->toBe('call_123');
});

test('message can be converted to array', function (): void {
    $message = new Message(Role::USER, 'Hello, world!');

    $array = $message->toArray();

    expect($array)->toBe([
        'role' => 'user',
        'content' => 'Hello, world!',
    ]);
});

test('message with tool call id can be converted to array', function (): void {
    $message = new Message(Role::TOOL, 'The weather is sunny.', null, 'call_123');

    $array = $message->toArray();

    expect($array)->toBe([
        'role' => 'tool',
        'content' => 'The weather is sunny.',
        'tool_call_id' => 'call_123',
    ]);
});

test('message can be created from array', function (): void {
    $array = [
        'role' => 'user',
        'content' => 'Hello, world!',
    ];

    $message = Message::fromArray($array);

    expect($message)->toBeInstanceOf(Message::class);
    expect($message->getRole())->toBe(Role::USER);
    expect($message->getContent())->toBe('Hello, world!');
    expect($message->getToolCallId())->toBeNull();
});

test('message with tool call id can be created from array', function (): void {
    $array = [
        'role' => 'tool',
        'content' => 'The weather is sunny.',
        'tool_call_id' => 'call_123',
    ];

    $message = Message::fromArray($array);

    expect($message)->toBeInstanceOf(Message::class);
    expect($message->getRole())->toBe(Role::TOOL);
    expect($message->getContent())->toBe('The weather is sunny.');
    expect($message->getToolCallId())->toBe('call_123');
});
