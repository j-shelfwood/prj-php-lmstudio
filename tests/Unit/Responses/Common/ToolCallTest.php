<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Http\Responses\Common\Function_;
use Shelfwood\LMStudio\Http\Responses\Common\ToolCall;

describe('ToolCall', function (): void {
    it('can be instantiated with id, type, and function', function (): void {
        $function = new Function_(
            name: 'get_weather',
            arguments: '{"location":"San Francisco"}'
        );

        $toolCall = new ToolCall(
            id: 'call_123',
            type: 'function',
            function: $function
        );

        expect($toolCall->id)->toBe('call_123');
        expect($toolCall->type)->toBe('function');
        expect($toolCall->function)->toBe($function);
    });

    it('can be created from an array', function (): void {
        $data = [
            'id' => 'call_456',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"location":"San Francisco","unit":"celsius"}',
            ],
        ];

        $toolCall = ToolCall::fromArray($data);

        expect($toolCall->id)->toBe('call_456');
        expect($toolCall->type)->toBe('function');
        expect($toolCall->function)->toBeInstanceOf(Function_::class);
        expect($toolCall->function->name)->toBe('get_weather');
        expect($toolCall->function->arguments)->toBe('{"location":"San Francisco","unit":"celsius"}');
    });

    it('handles missing data when created from array', function (): void {
        $toolCall = ToolCall::fromArray([]);

        expect($toolCall->id)->toBe('');
        expect($toolCall->type)->toBe('function');
        expect($toolCall->function)->toBeInstanceOf(Function_::class);
        expect($toolCall->function->name)->toBe('');
        expect($toolCall->function->arguments)->toBe('{}');
    });

    it('handles partial data when created from array', function (): void {
        $data = [
            'id' => 'call_789',
            // Missing type
            'function' => [
                'name' => 'get_weather',
                // Missing arguments
            ],
        ];

        $toolCall = ToolCall::fromArray($data);

        expect($toolCall->id)->toBe('call_789');
        expect($toolCall->type)->toBe('function');
        expect($toolCall->function)->toBeInstanceOf(Function_::class);
        expect($toolCall->function->name)->toBe('get_weather');
        expect($toolCall->function->arguments)->toBe('{}');
    });
});
