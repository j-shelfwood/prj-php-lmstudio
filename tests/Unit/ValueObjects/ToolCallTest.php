<?php

declare(strict_types=1);

use Shelfwood\LMStudio\ValueObjects\FunctionCall;
use Shelfwood\LMStudio\ValueObjects\ToolCall;

describe('ToolCall', function (): void {
    it('can be instantiated with id, type, and function', function (): void {
        $functionCall = new FunctionCall(
            name: 'get_weather',
            arguments: '{"location":"San Francisco"}'
        );

        $toolCall = new ToolCall(
            id: 'call_123',
            type: 'function',
            function: $functionCall
        );

        expect($toolCall->id)->toBe('call_123');
        expect($toolCall->type)->toBe('function');
        expect($toolCall->function)->toBe($functionCall);
    });

    it('can create a function tool call with static method', function (): void {
        $toolCall = ToolCall::function(
            name: 'get_weather',
            arguments: '{"location":"San Francisco"}',
            id: 'call_456'
        );

        expect($toolCall)->toBeInstanceOf(ToolCall::class);
        expect($toolCall->id)->toBe('call_456');
        expect($toolCall->type)->toBe('function');
        expect($toolCall->function)->toBeInstanceOf(FunctionCall::class);
        expect($toolCall->function->name)->toBe('get_weather');
        expect($toolCall->function->arguments)->toBe('{"location":"San Francisco"}');
    });

    it('generates a unique ID when not provided', function (): void {
        $toolCall = ToolCall::function(
            name: 'get_weather',
            arguments: '{"location":"San Francisco"}'
        );

        expect($toolCall->id)->toStartWith('call_');
        expect(strlen($toolCall->id) > 5)->toBeTrue();
    });

    it('serializes to JSON correctly', function (): void {
        $functionCall = new FunctionCall(
            name: 'get_weather',
            arguments: '{"location":"San Francisco"}'
        );

        $toolCall = new ToolCall(
            id: 'call_123',
            type: 'function',
            function: $functionCall
        );

        $json = $toolCall->jsonSerialize();

        expect($json)->toBeArray();
        expect($json['id'])->toBe('call_123');
        expect($json['type'])->toBe('function');
        expect($json['function'])->toBeArray();
        expect($json['function']['name'])->toBe('get_weather');
        expect($json['function']['arguments'])->toBe('{"location":"San Francisco"}');
    });
});
