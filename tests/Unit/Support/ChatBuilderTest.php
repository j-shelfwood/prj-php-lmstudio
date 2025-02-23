<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\Support\ChatBuilder;

beforeEach(function () {
    $this->mockHandler = new MockHandler;
    $handlerStack = HandlerStack::create($this->mockHandler);
    $client = new Client(['handler' => $handlerStack]);

    $this->lmstudio = new LMStudio(
        host: 'localhost',
        port: 1234,
        timeout: 30
    );

    // Replace the client with our mocked version
    $reflection = new ReflectionClass($this->lmstudio);
    $property = $reflection->getProperty('client');
    $property->setAccessible(true);
    $property->setValue($this->lmstudio, $client);

    $this->chat = $this->lmstudio->chat();
});

test('it can be instantiated', function () {
    expect($this->chat)->toBeInstanceOf(ChatBuilder::class);
});

test('it can set model', function () {
    $this->chat->withModel('test-model');

    $reflection = new ReflectionClass($this->chat);
    $property = $reflection->getProperty('model');
    $property->setAccessible(true);

    expect($property->getValue($this->chat))->toBe('test-model');
});

test('it can set messages', function () {
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
    ];

    $this->chat->withMessages($messages);

    $reflection = new ReflectionClass($this->chat);
    $property = $reflection->getProperty('messages');
    $property->setAccessible(true);

    expect($property->getValue($this->chat))->toBe($messages);
});

test('it can add a single message', function () {
    $this->chat->addMessage('user', 'Hello');

    $reflection = new ReflectionClass($this->chat);
    $property = $reflection->getProperty('messages');
    $property->setAccessible(true);

    expect($property->getValue($this->chat))->toBe([
        ['role' => 'user', 'content' => 'Hello'],
    ]);
});

test('it can set tools', function () {
    $tools = [
        [
            'type' => 'function',
            'function' => [
                'name' => 'test',
                'parameters' => ['type' => 'object'],
            ],
        ],
    ];

    $this->chat->withTools($tools);

    $reflection = new ReflectionClass($this->chat);
    $property = $reflection->getProperty('tools');
    $property->setAccessible(true);

    expect($property->getValue($this->chat))->toBe($tools);
});

test('it can register tool handlers', function () {
    $handler = fn () => true;
    $this->chat->withToolHandler('test', $handler);

    $reflection = new ReflectionClass($this->chat);
    $property = $reflection->getProperty('toolHandlers');
    $property->setAccessible(true);

    expect($property->getValue($this->chat))->toHaveKey('test');
});

test('it can send chat completion', function () {
    $this->mockHandler->append(new Response(200, [], json_encode((object) [
        'choices' => [
            (object) [
                'message' => (object) [
                    'content' => 'Test response',
                ],
            ],
        ],
    ])));

    $response = $this->chat
        ->withModel('test-model')
        ->withMessages([['role' => 'user', 'content' => 'Hello']])
        ->send();

    expect($response->choices[0]->message->content)->toBe('Test response');
});

test('it can stream chat completion', function () {
    $events = [
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['content' => 'Hello']]]])."\n",
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['content' => ' World']]]])."\n",
        "[DONE]\n",
    ];

    $this->mockHandler->append(new Response(200, [], implode('', $events)));

    $content = '';
    $response = $this->chat
        ->withModel('test-model')
        ->withMessages([['role' => 'user', 'content' => 'Hello']])
        ->stream()
        ->send();

    foreach ($response as $chunk) {
        if (is_string($chunk)) {
            $content .= $chunk;
        }
    }

    expect($content)->toBe('Hello World');
});

test('it can handle tool calls', function () {
    // First response with tool call
    $toolCallEvents = [
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['tool_calls' => [(object) ['id' => '123', 'type' => 'function', 'function' => (object) ['name' => 'test']]]]]]])."\n",
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['tool_calls' => [(object) ['function' => (object) ['arguments' => '{"arg":"value"}']]]]]]])."\n",
        "[DONE]\n",
    ];

    $this->mockHandler->append(new Response(200, [], implode('', $toolCallEvents)));

    $toolCalled = false;
    $content = '';

    $response = $this->chat
        ->withModel('test-model')
        ->withMessages([['role' => 'user', 'content' => 'Test tools']])
        ->withTools([
            [
                'type' => 'function',
                'function' => [
                    'name' => 'test',
                    'parameters' => ['type' => 'object'],
                ],
            ],
        ])
        ->withToolHandler('test', function ($args) use (&$toolCalled) {
            $toolCalled = true;
            expect($args)->toBe(['arg' => 'value']);

            return ['status' => 'success'];
        })
        ->stream()
        ->send();

    foreach ($response as $chunk) {
        if (is_string($chunk)) {
            $content .= $chunk;
        }
    }

    expect($toolCalled)->toBeTrue()
        ->and($content)->toContain('{"status":"success"}');
});
