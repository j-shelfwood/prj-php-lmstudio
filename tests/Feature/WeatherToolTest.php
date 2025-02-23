<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\LMStudio;

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
});

test('it can get weather for a location', function () {
    // First response with tool call
    $toolCallEvents = [
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['tool_calls' => [(object) ['id' => '123', 'type' => 'function', 'function' => (object) ['name' => 'get_current_weather']]]]]]])."\n",
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['tool_calls' => [(object) ['function' => (object) ['arguments' => '{"location":"Amsterdam"}']]]]]]])."\n",
        "[DONE]\n",
    ];

    $this->mockHandler->append(new Response(200, [], implode('', $toolCallEvents)));

    $weatherData = null;
    $content = '';

    $response = $this->lmstudio->chat()
        ->withModel('test-model')
        ->withMessages([
            ['role' => 'system', 'content' => 'You are a helpful weather assistant.'],
            ['role' => 'user', 'content' => 'What is the weather in Amsterdam?'],
        ])
        ->withTools([
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_weather',
                    'description' => 'Get the current weather in a location',
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
        ])
        ->withToolHandler('get_current_weather', function ($args) use (&$weatherData) {
            expect($args)->toHaveKey('location')
                ->and($args['location'])->toBe('Amsterdam');

            $weatherData = [
                'temperature' => 20,
                'condition' => 'sunny',
                'location' => $args['location'],
            ];

            return $weatherData;
        })
        ->stream()
        ->send();

    foreach ($response as $chunk) {
        if (is_string($chunk)) {
            $content .= $chunk;
        }
    }

    expect($weatherData)->not->toBeNull()
        ->and($weatherData['location'])->toBe('Amsterdam')
        ->and($content)->toContain('{"temperature":20,"condition":"sunny","location":"Amsterdam"}');
});

test('it handles multiple weather requests in a conversation', function () {
    // First request for Amsterdam
    $amsterdamToolCallEvents = [
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['tool_calls' => [(object) ['id' => '123', 'type' => 'function', 'function' => (object) ['name' => 'get_current_weather']]]]]]])."\n",
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['tool_calls' => [(object) ['function' => (object) ['arguments' => '{"location":"Amsterdam"}']]]]]]])."\n",
        "[DONE]\n",
    ];

    // Second request for London
    $londonToolCallEvents = [
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['tool_calls' => [(object) ['id' => '124', 'type' => 'function', 'function' => (object) ['name' => 'get_current_weather']]]]]]])."\n",
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['tool_calls' => [(object) ['function' => (object) ['arguments' => '{"location":"London"}']]]]]]])."\n",
        "[DONE]\n",
    ];

    $this->mockHandler->append(
        new Response(200, [], implode('', $amsterdamToolCallEvents)),
        new Response(200, [], implode('', $londonToolCallEvents))
    );

    $locations = [];
    $responses = [];

    $chat = $this->lmstudio->chat()
        ->withModel('test-model')
        ->withTools([
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_weather',
                    'description' => 'Get the current weather in a location',
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
        ])
        ->withToolHandler('get_current_weather', function ($args) use (&$locations) {
            $locations[] = $args['location'];

            return [
                'temperature' => $args['location'] === 'Amsterdam' ? 20 : 18,
                'condition' => $args['location'] === 'Amsterdam' ? 'sunny' : 'cloudy',
                'location' => $args['location'],
            ];
        });

    // First request
    $response = $chat->withMessages([
        ['role' => 'system', 'content' => 'You are a helpful weather assistant.'],
        ['role' => 'user', 'content' => 'What is the weather in Amsterdam?'],
    ])
        ->stream()
        ->send();

    foreach ($response as $chunk) {
        if (is_string($chunk)) {
            $responses[] = $chunk;
        }
    }

    // Second request
    $response = $chat->addMessage('user', 'And what about London?')
        ->stream()
        ->send();

    foreach ($response as $chunk) {
        if (is_string($chunk)) {
            $responses[] = $chunk;
        }
    }

    expect($locations)->toBe(['Amsterdam', 'London'])
        ->and(implode('', $responses))->toContain('Amsterdam')
        ->and(implode('', $responses))->toContain('London')
        ->and(implode('', $responses))->toContain('sunny')
        ->and(implode('', $responses))->toContain('cloudy');
});
