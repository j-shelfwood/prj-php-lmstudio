<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Enums\FinishReason;
use Shelfwood\LMStudio\Enums\ToolType;
use Shelfwood\LMStudio\ValueObjects\StreamChunk;
use Shelfwood\LMStudio\ValueObjects\ToolCall;

test('it can be instantiated with raw chunk data', function (): void {
    $rawChunk = ['choices' => [['delta' => ['content' => 'Hello']]]];
    $chunk = new StreamChunk($rawChunk);

    expect($chunk)->toBeInstanceOf(StreamChunk::class);
    expect($chunk->getRawChunk())->toBe($rawChunk);
});

test('it can detect content in chunk', function (): void {
    $chunk = new StreamChunk(['choices' => [['delta' => ['content' => 'Hello']]]]);

    expect($chunk->hasContent())->toBeTrue();
    expect($chunk->getContent())->toBe('Hello');
});

test('it returns null for content when no content is present', function (): void {
    $chunk = new StreamChunk(['choices' => [['delta' => []]]]);

    expect($chunk->hasContent())->toBeFalse();
    expect($chunk->getContent())->toBeNull();
});

test('it can detect tool calls in chunk', function (): void {
    $chunk = new StreamChunk([
        'choices' => [[
            'delta' => [
                'tool_calls' => [[
                    'index' => 0,
                    'id' => 'call_123',
                    'type' => 'function',
                    'function' => [
                        'name' => 'test_tool',
                        'arguments' => '{"param":"test"}',
                    ],
                ]],
            ],
        ]],
    ]);

    expect($chunk->hasToolCalls())->toBeTrue();

    $toolCalls = $chunk->getToolCalls();
    expect($toolCalls)->toBeArray();
    expect($toolCalls)->toHaveCount(1);

    $toolCall = $toolCalls[0];
    expect($toolCall)->toBeInstanceOf(ToolCall::class);
    expect($toolCall->id)->toBe('call_123');
    expect($toolCall->type)->toBe(ToolType::FUNCTION);
    expect($toolCall->function->name)->toBe('test_tool');
    expect($toolCall->function->arguments)->toBe('{"param":"test"}');
});

test('it returns empty array for tool calls when no tool calls are present', function (): void {
    $chunk = new StreamChunk(['choices' => [['delta' => []]]]);

    expect($chunk->hasToolCalls())->toBeFalse();
    expect($chunk->getToolCalls())->toBeArray();
    expect($chunk->getToolCalls())->toBeEmpty();
});

test('it can detect completion in chunk', function (): void {
    $chunk = new StreamChunk(['choices' => [['finish_reason' => 'stop']]]);

    expect($chunk->isComplete())->toBeTrue();
    expect($chunk->getFinishReason())->toBeInstanceOf(FinishReason::class);
    expect($chunk->getFinishReason()->value)->toBe('stop');
});

test('it returns null for finish reason when not complete', function (): void {
    $chunk = new StreamChunk(['choices' => [['delta' => ['content' => 'Hello']]]]);

    expect($chunk->isComplete())->toBeFalse();
    expect($chunk->getFinishReason())->toBeNull();
});

test('it can detect errors in chunk', function (): void {
    $chunk = new StreamChunk(['error' => 'Test error']);

    expect($chunk->hasError())->toBeTrue();
    expect($chunk->getError())->toBe('Unknown error');
});

test('it returns null for error when no error is present', function (): void {
    $chunk = new StreamChunk(['choices' => [['delta' => ['content' => 'Hello']]]]);

    expect($chunk->hasError())->toBeFalse();
    expect($chunk->getError())->toBeNull();
});

test('it can be serialized to JSON', function (): void {
    $rawChunk = ['choices' => [['delta' => ['content' => 'Hello']]]];
    $chunk = new StreamChunk($rawChunk);

    $serialized = $chunk->jsonSerialize();
    expect($serialized)->toBeArray();

    // Test JSON encoding
    $json = json_encode($chunk);
    expect($json)->toBeString();
    expect(json_decode($json, true))->toBeArray();
});
