<?php

declare(strict_types=1);

namespace Tests\Feature;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Chat\Role;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\DTOs\Tool\ToolFunction;
use Shelfwood\LMStudio\Http\ApiClient;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;
use Shelfwood\LMStudio\LMStudio;

beforeEach(function (): void {
    // Create a mock handler and handler stack
    $this->mock = new MockHandler;
    $handlerStack = HandlerStack::create($this->mock);

    // Create dependencies with mocked client
    $apiClient = new ApiClient(['handler' => $handlerStack]);
    $streamingHandler = new StreamingResponseHandler;
    $config = new Config(host: 'localhost', port: 1234, timeout: 30);

    // Create LMStudio instance with dependencies
    $this->lmstudio = new LMStudio(
        config: $config,
        apiClient: $apiClient,
        streamingHandler: $streamingHandler
    );
});

test('it can get weather for a location', function (): void {
    $weatherTool = new ToolFunction(
        name: 'get_current_weather',
        description: 'Get the current weather',
        parameters: [
            'location' => [
                'type' => 'string',
                'description' => 'The location to get weather for',
            ],
        ],
        required: ['location']
    );

    $events = [
        'data: '.json_encode([
            'choices' => [[
                'delta' => [
                    'tool_calls' => [[
                        'id' => '123',
                        'type' => 'function',
                        'function' => ['name' => 'get_current_weather'],
                    ]],
                ],
            ]],
        ]).\PHP_EOL,
        'data: '.json_encode([
            'choices' => [[
                'delta' => [
                    'tool_calls' => [[
                        'function' => ['arguments' => '{"location":"London"}'],
                    ]],
                ],
            ]],
        ]).\PHP_EOL,
        'data: [DONE]'.\PHP_EOL,
    ];

    $this->mock->append(new Response(200, [], implode('', $events)));

    $messages = [];

    foreach ($this->lmstudio->chat()
        ->withModel('test-model')
        ->withMessages([new Message(Role::USER, 'What\'s the weather in London?')])
        ->withTools([$weatherTool])
        ->withToolHandler('get_current_weather', function (array $args) {
            return ['temperature' => 20, 'condition' => 'sunny'];
        })
        ->stream()
        ->send() as $message) {
        $messages[] = $message;
    }

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->type)->toBe('tool_call')
        ->and($messages[0]->toolCall->function->name)->toBe('get_current_weather')
        ->and($messages[1]->type)->toBe('done');

    $args = $messages[0]->toolCall->function->validateArguments('{"location":"London"}');
    expect($args)->toBe(['location' => 'London']);
});

test('it handles multiple weather requests in a conversation', function (): void {
    $weatherTool = new ToolFunction(
        name: 'get_current_weather',
        description: 'Get the current weather',
        parameters: [
            'location' => [
                'type' => 'string',
                'description' => 'The location to get weather for',
            ],
        ],
        required: ['location']
    );

    $events = [
        'data: '.json_encode([
            'choices' => [[
                'delta' => [
                    'tool_calls' => [[
                        'id' => '123',
                        'type' => 'function',
                        'function' => ['name' => 'get_current_weather'],
                    ]],
                ],
            ]],
        ]).\PHP_EOL,
        'data: '.json_encode([
            'choices' => [[
                'delta' => [
                    'tool_calls' => [[
                        'function' => ['arguments' => '{"location":"London"}'],
                    ]],
                ],
            ]],
        ]).\PHP_EOL,
        'data: '.json_encode([
            'choices' => [[
                'delta' => [
                    'tool_calls' => [[
                        'id' => '456',
                        'type' => 'function',
                        'function' => ['name' => 'get_current_weather'],
                    ]],
                ],
            ]],
        ]).\PHP_EOL,
        'data: '.json_encode([
            'choices' => [[
                'delta' => [
                    'tool_calls' => [[
                        'function' => ['arguments' => '{"location":"Paris"}'],
                    ]],
                ],
            ]],
        ]).\PHP_EOL,
        'data: [DONE]'.\PHP_EOL,
    ];

    $this->mock->append(new Response(200, [], implode('', $events)));

    $messages = [];

    foreach ($this->lmstudio->chat()
        ->withModel('test-model')
        ->withMessages([new Message(Role::USER, 'Compare the weather in London and Paris')])
        ->withTools([$weatherTool])
        ->withToolHandler('get_current_weather', function (array $args) {
            return [
                'temperature' => $args['location'] === 'London' ? 20 : 25,
                'condition' => $args['location'] === 'London' ? 'sunny' : 'cloudy',
            ];
        })
        ->stream()
        ->send() as $message) {
        $messages[] = $message;
    }

    expect($messages)->toHaveCount(3)
        ->and($messages[0]->type)->toBe('tool_call')
        ->and($messages[0]->toolCall->function->name)->toBe('get_current_weather')
        ->and($messages[1]->type)->toBe('tool_call')
        ->and($messages[1]->toolCall->function->name)->toBe('get_current_weather')
        ->and($messages[2]->type)->toBe('done');

    $args1 = $messages[0]->toolCall->function->validateArguments('{"location":"London"}');
    $args2 = $messages[1]->toolCall->function->validateArguments('{"location":"Paris"}');

    expect($args1)->toBe(['location' => 'London'])
        ->and($args2)->toBe(['location' => 'Paris']);
});
