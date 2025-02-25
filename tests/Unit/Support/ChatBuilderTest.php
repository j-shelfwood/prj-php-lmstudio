<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Chat\Role;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\DTOs\Tool\ToolFunction;
use Shelfwood\LMStudio\Exceptions\ValidationException;
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

    $this->chatBuilder = $this->lmstudio->chat();
});

test('it can be instantiated', function (): void {
    $chat = $this->lmstudio->chat();
    expect($chat)->toBeObject();
});

test('it can set model', function (): void {
    $chat = $this->lmstudio->chat()->withModel('test-model');
    expect($chat)->toBeObject();

    expect(fn () => $this->lmstudio->chat()->withModel(''))
        ->toThrow(ValidationException::class);
});

test('it can set messages', function (): void {
    $messages = [
        new Message(Role::SYSTEM, 'System message'),
        new Message(Role::USER, 'User message'),
    ];

    $chat = $this->lmstudio->chat()->withMessages($messages);
    expect($chat)->toBeObject();

    // Test with array format
    $chat = $this->lmstudio->chat()->withMessages([
        ['role' => 'system', 'content' => 'System message'],
        ['role' => 'user', 'content' => 'User message'],
    ]);
    expect($chat)->toBeObject();
});

test('it can add a single message', function (): void {
    $chat = $this->lmstudio->chat()->addMessage(Role::USER, 'Test message');
    expect($chat)->toBeObject();

    expect(fn () => $this->lmstudio->chat()->addMessage(Role::USER, ''))
        ->toThrow(ValidationException::class);
});

test('it can set tools', function (): void {
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

    $chat = $this->lmstudio->chat()->withTools([$weatherTool]);
    expect($chat)->toBeObject();

    // Test with array format
    $chat = $this->lmstudio->chat()->withTools([
        [
            'id' => 'test-id',
            'type' => 'function',
            'function' => [
                'name' => 'get_current_weather',
                'description' => 'Get the current weather',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => [
                            'type' => 'string',
                            'description' => 'The location to get weather for',
                        ],
                    ],
                    'required' => ['location'],
                ],
            ],
        ],
    ]);
    expect($chat)->toBeObject();
});

test('it can register tool handlers', function (): void {
    $chat = $this->lmstudio->chat()->withToolHandler('test', fn () => true);
    expect($chat)->toBeObject();

    expect(fn () => $this->lmstudio->chat()->withToolHandler('', fn () => true))
        ->toThrow(ValidationException::class);
});

test('it can send chat completion', function (): void {
    $this->mock->append(new Response(200, [], json_encode([
        'id' => 'chat-1',
        'object' => 'chat.completion',
        'created' => 1234567890,
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Hello!',
                ],
            ],
        ],
    ])));

    $result = $this->lmstudio->chat()
        ->withModel('test-model')
        ->withMessages([new Message(Role::USER, 'Hi!')])
        ->send();

    expect($result->id)->toBe('chat-1')
        ->and($result->object)->toBe('chat.completion')
        ->and($result->choices[0]->message->content)->toBe('Hello!');

    expect(fn () => $this->lmstudio->chat()->send())
        ->toThrow(ValidationException::class);
});

test('it can stream chat completion', function (): void {
    $events = [
        'data: '.json_encode([
            'choices' => [[
                'delta' => ['content' => 'Hello'],
            ]],
        ]).\PHP_EOL,
        'data: '.json_encode([
            'choices' => [[
                'delta' => ['content' => ' world!'],
            ]],
        ]).\PHP_EOL,
        'data: [DONE]'.\PHP_EOL,
    ];

    $this->mock->append(new Response(200, [], implode('', $events)));

    $messages = [];

    foreach ($this->lmstudio->chat()
        ->withModel('test-model')
        ->withMessages([new Message(Role::USER, 'Hi!')])
        ->stream()
        ->send() as $message) {
        $messages[] = $message;
    }

    expect($messages)->toHaveCount(3)
        ->and($messages[0]->type)->toBe('message')
        ->and($messages[0]->message->content)->toBe('Hello')
        ->and($messages[1]->type)->toBe('message')
        ->and($messages[1]->message->content)->toBe(' world!')
        ->and($messages[2]->type)->toBe('done');
});

test('it can handle tool calls', function (): void {
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
