<?php

declare(strict_types=1);

namespace Tests\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\Contracts\ApiClientInterface;
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Chat\Role;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\DTOs\Model\ModelInfo;
use Shelfwood\LMStudio\DTOs\Model\ModelList;
use Shelfwood\LMStudio\DTOs\Response\ChatCompletion;
use Shelfwood\LMStudio\DTOs\Tool\ToolCall;
use Shelfwood\LMStudio\DTOs\Tool\ToolFunction;
use Shelfwood\LMStudio\Exceptions\ValidationException;
use Shelfwood\LMStudio\Http\ApiClient;
use Shelfwood\LMStudio\LMStudio;

beforeEach(function (): void {
    $this->mockHandler = new MockHandler;
    $handlerStack = HandlerStack::create($this->mockHandler);

    $this->lmstudio = new LMStudio(
        config: new Config(
            host: 'localhost',
            port: 1234,
            timeout: 30
        ),
        apiClient: new ApiClient(['handler' => $handlerStack])
    );
});

test('it can be instantiated with default config', function (): void {
    $lmstudio = LMStudio::create();
    $config = $lmstudio->getConfig();

    expect($config->host)->toBe('localhost')
        ->and($config->port)->toBe(1234)
        ->and($config->timeout)->toBe(30);
});

test('it can list models', function (): void {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'object' => 'list',
        'data' => [
            [
                'id' => 'model-1',
                'object' => 'model',
                'created' => 1234567890,
                'owned_by' => 'owner',
            ],
        ],
    ])));

    $result = $this->lmstudio->listModels();

    expect($result)->toBeInstanceOf(ModelList::class)
        ->and($result->object)->toBe('list')
        ->and($result->data)->toHaveCount(1)
        ->and($result->data[0])->toBeInstanceOf(ModelInfo::class)
        ->and($result->data[0]->id)->toBe('model-1');
});

test('it can get model information', function (): void {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'id' => 'model-1',
        'object' => 'model',
        'created' => 1234567890,
        'owned_by' => 'owner',
    ])));

    $result = $this->lmstudio->getModel('model-1');

    expect($result)->toBeInstanceOf(ModelInfo::class)
        ->and($result->id)->toBe('model-1')
        ->and($result->object)->toBe('model')
        ->and($result->created)->toBe(1234567890)
        ->and($result->ownedBy)->toBe('owner');
});

test('it can create chat completion', function (): void {
    $this->mockHandler->append(new Response(200, [], json_encode([
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

    $result = $this->lmstudio->createChatCompletion(
        messages: [new Message(Role::USER, 'Hi!')],
        model: 'test-model'
    );

    expect($result->id)->toBe('chat-1')
        ->and($result->object)->toBe('chat.completion')
        ->and($result->choices[0]->message->content)->toBe('Hello!');
});

test('it can create text completion', function (): void {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'id' => 'cmpl-1',
        'object' => 'text_completion',
        'created' => 1234567890,
        'choices' => [
            [
                'text' => 'Hello world!',
                'index' => 0,
                'finish_reason' => 'stop',
            ],
        ],
    ])));

    $result = $this->lmstudio->createTextCompletion(
        prompt: 'Say hello',
        model: 'test-model'
    );

    expect($result['id'])->toBe('cmpl-1')
        ->and($result['object'])->toBe('text_completion')
        ->and($result['choices'][0]['text'])->toBe('Hello world!');
});

test('it can stream chat completion', function (): void {
    $events = [
        'data: '.json_encode(['choices' => [['delta' => ['content' => 'Hello']]]]).\PHP_EOL,
        'data: '.json_encode(['choices' => [['delta' => ['content' => ' world!']]]]).\PHP_EOL,
        'data: [DONE]'.\PHP_EOL,
    ];

    $this->mockHandler->append(new Response(200, [], implode('', $events)));

    $messages = [];

    foreach ($this->lmstudio->createChatCompletionStream(
        messages: [new Message(Role::USER, 'Hi!')],
        model: 'test-model'
    ) as $message) {
        $messages[] = $message;
    }

    expect($messages)->toHaveCount(3)
        ->and($messages[0]->type)->toBe('message')
        ->and($messages[0]->message->content)->toBe('Hello')
        ->and($messages[1]->type)->toBe('message')
        ->and($messages[1]->message->content)->toBe(' world!')
        ->and($messages[2]->type)->toBe('done');
});

test('it can stream text completion', function (): void {
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

    $this->mockHandler->append(new Response(200, [], implode('', $events)));

    $messages = [];

    foreach ($this->lmstudio->createTextCompletion(
        prompt: 'Say hello',
        model: 'test-model',
        options: ['stream' => true]
    ) as $message) {
        $messages[] = $message;
    }

    expect($messages)->toHaveCount(3)
        ->and($messages[0]->type)->toBe('message')
        ->and($messages[0]->message->content)->toBe('Hello')
        ->and($messages[1]->type)->toBe('message')
        ->and($messages[1]->message->content)->toBe(' world!')
        ->and($messages[2]->type)->toBe('done');
});

test('it fails text completion with empty prompt', function (): void {
    expect(fn () => $this->lmstudio->createTextCompletion(
        prompt: '',
        model: 'test-model'
    ))->toThrow(ValidationException::class, 'Prompt cannot be empty');
});

test('it fails text completion with empty model', function (): void {
    expect(fn () => $this->lmstudio->createTextCompletion(
        prompt: 'Say hello'
    ))->toThrow(ValidationException::class, 'Model must be specified for text completion');
});

test('it can stream chat completion with tool calls', function (): void {
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

    $this->mockHandler->append(new Response(200, [], implode('', $events)));

    $messages = [];

    foreach ($this->lmstudio->createChatCompletionStream(
        messages: [new Message(Role::USER, 'What\'s the weather in London?')],
        model: 'test-model',
        tools: [new ToolCall(uniqid('call_'), 'function', $weatherTool)]
    ) as $message) {
        $messages[] = $message;
    }

    expect($messages)
        ->toHaveCount(2)
        ->and($messages[0]->type)->toBe('tool_call')
        ->and($messages[0]->toolCall->function->name)->toBe('get_current_weather')
        ->and($messages[0]->toolCall->arguments)->toBe('{"location":"London"}')
        ->and($messages[1]->type)->toBe('done');
});

// -------------------------------
// REST API Tests
// -------------------------------

test('it can list rest models', function (): void {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'object' => 'list',
        'data' => [
            [
                'id' => 'model-1',
                'object' => 'model',
                'type' => 'llm',
                'publisher' => 'test',
                'arch' => 'test',
                'compatibility_type' => 'gguf',
                'quantization' => 'Q4_K_M',
                'state' => 'loaded',
                'max_context_length' => 4096,
            ],
        ],
    ])));

    $result = $this->lmstudio->listRestModels();

    expect($result['object'])->toBe('list')
        ->and($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['id'])->toBe('model-1')
        ->and($result['data'][0]['state'])->toBe('loaded');
});

test('it can get rest model', function (): void {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'id' => 'model-1',
        'object' => 'model',
        'type' => 'llm',
        'publisher' => 'test',
        'arch' => 'test',
        'compatibility_type' => 'gguf',
        'quantization' => 'Q4_K_M',
        'state' => 'loaded',
        'max_context_length' => 4096,
    ])));

    $result = $this->lmstudio->getRestModel('model-1');

    expect($result['id'])->toBe('model-1')
        ->and($result['state'])->toBe('loaded')
        ->and($result['max_context_length'])->toBe(4096);
});

test('it fails get rest model with empty model', function (): void {
    expect(fn () => $this->lmstudio->getRestModel(''))
        ->toThrow(ValidationException::class, 'Model identifier cannot be empty for REST API');
});

test('it can create rest chat completion', function (): void {
    $this->mockHandler->append(new Response(200, [], json_encode([
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
        'stats' => [
            'tokens_per_second' => 51.43,
            'time_to_first_token' => 0.111,
            'generation_time' => 0.954,
            'stop_reason' => 'eosFound',
        ],
    ])));

    $result = $this->lmstudio->createRestChatCompletion(
        messages: [new Message(Role::USER, 'Hi!')],
        model: 'test-model'
    );

    expect($result['id'])->toBe('chat-1')
        ->and($result['object'])->toBe('chat.completion')
        ->and($result['choices'][0]['message']['content'])->toBe('Hello!')
        ->and($result['stats']['tokens_per_second'])->toBe(51.43);
});

test('it can create rest completion', function (): void {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'id' => 'cmpl-1',
        'object' => 'text_completion',
        'created' => 1234567890,
        'choices' => [
            [
                'text' => 'Hello world!',
                'index' => 0,
                'finish_reason' => 'stop',
            ],
        ],
        'stats' => [
            'tokens_per_second' => 57.69,
            'time_to_first_token' => 0.299,
            'generation_time' => 0.156,
            'stop_reason' => 'maxPredictedTokensReached',
        ],
    ])));

    $result = $this->lmstudio->createRestCompletion(
        prompt: 'Say hello',
        model: 'test-model'
    );

    expect($result['id'])->toBe('cmpl-1')
        ->and($result['object'])->toBe('text_completion')
        ->and($result['choices'][0]['text'])->toBe('Hello world!')
        ->and($result['stats']['tokens_per_second'])->toBe(57.69);
});

test('it can create rest embeddings', function (): void {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'object' => 'list',
        'data' => [
            [
                'object' => 'embedding',
                'embedding' => [-0.016731, 0.028460, -0.140783],
                'index' => 0,
            ],
        ],
        'model' => 'test-model',
        'usage' => [
            'prompt_tokens' => 5,
            'total_tokens' => 5,
        ],
    ])));

    $result = $this->lmstudio->createRestEmbeddings(
        model: 'test-model',
        input: 'Test text'
    );

    expect($result['object'])->toBe('list')
        ->and($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['embedding'])->toBeArray()
        ->and($result['data'][0]['embedding'])->toHaveCount(3);
});

test('it fails rest embeddings with empty input', function (): void {
    expect(fn () => $this->lmstudio->createRestEmbeddings(
        model: 'test-model',
        input: ''
    ))->toThrow(ValidationException::class, 'Input cannot be empty for REST embeddings');
});

test('it fails rest embeddings with empty model', function (): void {
    expect(fn () => $this->lmstudio->createRestEmbeddings(
        model: '',
        input: 'Test text'
    ))->toThrow(ValidationException::class, 'Model identifier cannot be empty for REST embeddings');
});

test('it can create chat completion with TTL and auto-evict', function (): void {
    /** @var ApiClientInterface&\Mockery\MockInterface */
    $client = mock(ApiClientInterface::class);
    $client->shouldReceive('post')
        ->withArgs(function ($uri, $options) {
            return $uri === '/v1/chat/completions'
                && $options['json']['ttl'] === 3600
                && $options['json']['auto_evict'] === true;
        })
        ->andReturn(['choices' => [['message' => ['content' => 'Test response']]]]);

    /** @var LMStudio&\Mockery\MockInterface */
    $lmstudio = new LMStudio(
        config: new Config(defaultModel: 'test-model'),
        apiClient: $client
    );

    $response = $lmstudio->createChatCompletion([
        new Message(Role::USER, 'Test message'),
    ]);

    expect($response)->toBeInstanceOf(ChatCompletion::class)
        ->and($response->choices[0]->message->content)->toBe('Test response');
});

test('it can create chat completion with custom TTL and auto-evict', function (): void {
    /** @var ApiClientInterface&\Mockery\MockInterface */
    $client = mock(ApiClientInterface::class);
    $client->shouldReceive('post')
        ->withArgs(function ($uri, $options) {
            return $uri === '/v1/chat/completions'
                && $options['json']['ttl'] === 1800
                && $options['json']['auto_evict'] === false;
        })
        ->andReturn(['choices' => [['message' => ['content' => 'Test response']]]]);

    $lmstudio = new LMStudio(
        config: new Config(defaultModel: 'test-model'),
        apiClient: $client
    );

    $response = $lmstudio->createChatCompletion(
        messages: [new Message(Role::USER, 'Test message')],
        options: ['ttl' => 1800, 'auto_evict' => false]
    );

    expect($response)->toBeInstanceOf(ChatCompletion::class)
        ->and($response->choices[0]->message->content)->toBe('Test response');
});

test('it can create chat completion with default tool use mode', function (): void {
    /** @var ApiClientInterface&\Mockery\MockInterface */
    $client = mock(ApiClientInterface::class);
    $client->shouldReceive('post')
        ->withArgs(function ($uri, $options) {
            $toolMessage = $options['json']['messages'][1];

            return $uri === '/v1/chat/completions'
                && $toolMessage['role'] === 'user'
                && $toolMessage['default_tool_call'] === true;
        })
        ->andReturn(['choices' => [['message' => ['content' => 'Test response']]]]);

    $lmstudio = new LMStudio(
        config: new Config(defaultModel: 'test-model', toolUseMode: 'default'),
        apiClient: $client
    );

    $response = $lmstudio->createChatCompletion([
        new Message(Role::USER, 'Test message'),
        new Message(Role::TOOL, 'Tool response'),
    ]);

    expect($response)->toBeInstanceOf(ChatCompletion::class)
        ->and($response->choices[0]->message->content)->toBe('Test response');
});

test('it can create text completion with structured output', function (): void {
    $schema = [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'test_schema',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'integer'],
                ],
                'required' => ['name', 'age'],
            ],
        ],
    ];

    /** @var ApiClientInterface&\Mockery\MockInterface */
    $client = mock(ApiClientInterface::class);
    $client->shouldReceive('post')
        ->withArgs(function ($uri, $options) use ($schema) {
            return $uri === '/v1/completions'
                && $options['json']['response_format'] === $schema;
        })
        ->andReturn(['choices' => [['text' => '{"name":"John","age":30}']]]);

    $lmstudio = new LMStudio(
        config: new Config(defaultModel: 'test-model'),
        apiClient: $client
    );

    $response = $lmstudio->createTextCompletion(
        prompt: 'Generate a person object',
        options: ['response_format' => $schema]
    );

    expect($response)->toBeArray()
        ->and($response['choices'][0]['text'])->toBe('{"name":"John","age":30}');
});
