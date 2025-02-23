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

test('it can be instantiated with default config', function () {
    $lmstudio = new LMStudio;
    expect($lmstudio)->toBeInstanceOf(LMStudio::class);
});

test('it can list models', function () {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'data' => [
            [
                'id' => 'model1',
                'type' => 'llm',
                'publisher' => 'test',
            ],
        ],
    ])));

    $models = $this->lmstudio->listModels();
    expect($models)->toHaveKey('data')
        ->and($models['data'])->toBeArray()
        ->and($models['data'][0])->toHaveKey('id');
});

test('it can get model information', function () {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'id' => 'model1',
        'type' => 'llm',
        'publisher' => 'test',
    ])));

    $model = $this->lmstudio->getModel('model1');
    expect($model)->toHaveKey('id')
        ->and($model['id'])->toBe('model1');
});

test('it can create chat completion', function () {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'choices' => [
            [
                'message' => [
                    'content' => 'Test response',
                ],
            ],
        ],
    ])));

    $response = $this->lmstudio->createChatCompletion([
        'model' => 'test-model',
        'messages' => [
            ['role' => 'user', 'content' => 'Hello'],
        ],
    ]);

    expect($response->choices[0]->message->content)->toBe('Test response');
});

test('it can stream chat completion', function () {
    $events = [
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['content' => 'Hello']]]])."\n",
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['content' => ' World']]]])."\n",
        "[DONE]\n",
    ];

    $this->mockHandler->append(new Response(200, [], implode('', $events)));

    $response = $this->lmstudio->createChatCompletion([
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'Hello']],
        'stream' => true,
    ]);

    $content = '';
    foreach ($response as $data) {
        if (isset($data->choices[0]->delta->content)) {
            $content .= $data->choices[0]->delta->content;
        }
    }

    expect($content)->toBe('Hello World');
});

test('it can stream chat completion with tool calls', function () {
    $toolCallData = (object) [
        'choices' => [
            (object) [
                'delta' => (object) [
                    'tool_calls' => [
                        (object) [
                            'id' => '123',
                            'type' => 'function',
                            'function' => (object) ['name' => 'test'],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $toolCallArgsData = (object) [
        'choices' => [
            (object) [
                'delta' => (object) [
                    'tool_calls' => [
                        (object) [
                            'function' => (object) ['arguments' => '{"arg":"value"}'],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $events = [
        json_encode($toolCallData)."\n",
        json_encode($toolCallArgsData)."\n",
        "[DONE]\n",
    ];

    $this->mockHandler->append(new Response(200, [], implode('', $events)));

    $response = $this->lmstudio->createChatCompletion([
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'Test tools']],
        'stream' => true,
        'tools' => [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'test',
                    'parameters' => ['type' => 'object'],
                ],
            ],
        ],
    ]);

    $toolCall = [
        'id' => null,
        'type' => 'function',
        'function' => [
            'name' => '',
            'arguments' => '',
        ],
    ];

    foreach ($response as $data) {
        if (isset($data->choices[0]->delta->tool_calls)) {
            $delta = $data->choices[0]->delta->tool_calls[0];

            if (isset($delta->id)) {
                $toolCall['id'] = $delta->id;
            }
            if (isset($delta->function->name)) {
                $toolCall['function']['name'] = $delta->function->name;
            }
            if (isset($delta->function->arguments)) {
                $toolCall['function']['arguments'] = $delta->function->arguments;
            }
        }
    }

    expect($toolCall)->toHaveKey('id')
        ->and($toolCall['function']['name'])->toBe('test')
        ->and($toolCall['function']['arguments'])->toBe('{"arg":"value"}');
});
