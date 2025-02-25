<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use GuzzleHttp\Psr7\Response;
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

    expect($messages)->toHaveCount(3)
        ->and($messages[0]->type)->toBe('message')
        ->and($messages[0]->message->content)->toBe('Hello')
        ->and($messages[1]->type)->toBe('message')
        ->and($messages[1]->message->content)->toBe(' world!')
        ->and($messages[2]->type)->toBe('done');
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

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->type)->toBe('tool_call')
        ->and($messages[0]->toolCall->function->name)->toBe('test_tool')
        ->and($messages[0]->toolCall->arguments)->toBe('{"arg":"value"}')
        ->and($messages[1]->type)->toBe('done');
});

test('it ignores invalid json lines', function (): void {
    $events = [
        'data: invalid json'.\PHP_EOL,
        'data: '.json_encode(['choices' => [['delta' => ['content' => 'Hello']]]]).\PHP_EOL,
        'data: [DONE]'.\PHP_EOL,
    ];

    $response = new Response(200, [], implode('', $events));

    $messages = iterator_to_array($this->handler->handle($response));

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->type)->toBe('message')
        ->and($messages[0]->message->content)->toBe('Hello')
        ->and($messages[1]->type)->toBe('done');
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

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->type)->toBe('message')
        ->and($messages[0]->message->content)->toBe('Hello')
        ->and($messages[1]->type)->toBe('done');
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

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->type)->toBe('tool_call')
        ->and($messages[0]->toolCall->function->name)->toBe('test_tool')
        ->and($messages[0]->toolCall->arguments)->toBe('{"arg":"value"}')
        ->and($messages[1]->type)->toBe('done');
});
