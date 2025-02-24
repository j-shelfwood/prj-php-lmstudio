<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Tool\ToolCall;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;

beforeEach(function (): void {
    $this->handler = new StreamingResponseHandler;
});

test('it can handle content stream', function (): void {
    $events = [
        'data: '.json_encode(['choices' => [['delta' => ['content' => 'Hello']]]]).\PHP_EOL,
        'data: '.json_encode(['choices' => [['delta' => ['content' => ' world!']]]]).\PHP_EOL,
        'data: [DONE]'.\PHP_EOL,
    ];

    $response = new Response(200, [], implode('', $events));

    $messages = iterator_to_array($this->handler->handle($response));

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toBeInstanceOf(Message::class)
        ->and($messages[0]->content)->toBe('Hello')
        ->and($messages[1]->content)->toBe(' world!');
});

test('it can handle tool call stream', function (): void {
    $events = [
        'data: '.json_encode([
            'choices' => [[
                'delta' => [
                    'tool_calls' => [[
                        'id' => '123',
                        'type' => 'function',
                        'function' => ['name' => 'test_tool'],
                    ]],
                ],
            ]],
        ]).\PHP_EOL,
        'data: '.json_encode([
            'choices' => [[
                'delta' => [
                    'tool_calls' => [[
                        'function' => ['arguments' => '{"arg":"value"}'],
                    ]],
                ],
            ]],
        ]).\PHP_EOL,
        'data: [DONE]'.\PHP_EOL,
    ];

    $response = new Response(200, [], implode('', $events));

    $messages = iterator_to_array($this->handler->handle($response));

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toBeInstanceOf(ToolCall::class)
        ->and($messages[0]->function->name)->toBe('test_tool')
        ->and($messages[0]->arguments)->toBe('{"arg":"value"}');
});

test('it ignores invalid json lines', function (): void {
    $events = [
        'data: invalid json'.\PHP_EOL,
        'data: '.json_encode(['choices' => [['delta' => ['content' => 'Hello']]]]).\PHP_EOL,
        'data: [DONE]'.\PHP_EOL,
    ];

    $response = new Response(200, [], implode('', $events));

    $messages = iterator_to_array($this->handler->handle($response));

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toBeInstanceOf(Message::class)
        ->and($messages[0]->content)->toBe('Hello');
});

test('it handles empty lines', function (): void {
    $events = [
        \PHP_EOL,
        'data: '.json_encode(['choices' => [['delta' => ['content' => 'Hello']]]]).\PHP_EOL,
        \PHP_EOL,
        'data: [DONE]'.\PHP_EOL,
    ];

    $response = new Response(200, [], implode('', $events));

    $messages = iterator_to_array($this->handler->handle($response));

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toBeInstanceOf(Message::class)
        ->and($messages[0]->content)->toBe('Hello');
});

test('it handles partial tool calls', function (): void {
    $events = [
        'data: '.json_encode([
            'choices' => [[
                'delta' => [
                    'tool_calls' => [[
                        'id' => '123',
                        'type' => 'function',
                        'function' => ['name' => 'test_tool'],
                    ]],
                ],
            ]],
        ]).\PHP_EOL,
        'data: '.json_encode([
            'choices' => [[
                'delta' => [
                    'tool_calls' => [[
                        'function' => ['arguments' => '{"arg":'],
                    ]],
                ],
            ]],
        ]).\PHP_EOL,
        'data: '.json_encode([
            'choices' => [[
                'delta' => [
                    'tool_calls' => [[
                        'function' => ['arguments' => '"value"}'],
                    ]],
                ],
            ]],
        ]).\PHP_EOL,
        'data: [DONE]'.\PHP_EOL,
    ];

    $response = new Response(200, [], implode('', $events));

    $messages = iterator_to_array($this->handler->handle($response));

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toBeInstanceOf(ToolCall::class)
        ->and($messages[0]->function->name)->toBe('test_tool')
        ->and($messages[0]->arguments)->toBe('{"arg":"value"}');
});
