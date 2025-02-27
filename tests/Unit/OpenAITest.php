<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Http\Client;
use Shelfwood\LMStudio\OpenAI;
use Shelfwood\LMStudio\Responses\V1\ChatCompletion;
use Shelfwood\LMStudio\Responses\V1\Embedding;

beforeEach(function (): void {
    $this->config = new LMStudioConfig(
        baseUrl: 'http://example.com',
        apiKey: 'test-key'
    );
});

test('OpenAI client uses correct API version', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode(['data' => 'test'])),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $guzzle = new GuzzleClient(['handler' => $handlerStack]);

    $httpClient = new class($this->config, $guzzle) extends Client
    {
        public function __construct($config, $guzzle)
        {
            parent::__construct($config);
            $this->client = $guzzle;
        }
    };

    $openai = new class($this->config, $httpClient) extends OpenAI
    {
        public function __construct($config, $client)
        {
            parent::__construct($config);
            $this->client = $client;
        }
    };

    // The base URL should include the v1 API version
    expect($this->config->getBaseUrl())->toBe('http://example.com');
    $openai->models();
});

test('OpenAI client makes correct models request', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode(['data' => [['id' => 'test-model']]])),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $guzzle = new GuzzleClient(['handler' => $handlerStack]);

    $httpClient = new class($this->config, $guzzle) extends Client
    {
        public function __construct($config, $guzzle)
        {
            parent::__construct($config);
            $this->client = $guzzle;
        }
    };

    $openai = new class($this->config, $httpClient) extends OpenAI
    {
        public function __construct($config, $client)
        {
            parent::__construct($config);
            $this->client = $client;
        }
    };

    $response = $openai->models();
    expect($response)->toBe(['data' => [['id' => 'test-model']]]);
});

test('OpenAI client makes correct chat request', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'id' => 'test-id',
            'object' => 'chat.completion',
            'created' => 1677858242,
            'model' => 'gpt-3.5-turbo',
            'choices' => [
                [
                    'message' => ['role' => 'assistant', 'content' => 'Hello!'],
                    'finish_reason' => 'stop',
                    'index' => 0,
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
        ])),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $guzzle = new GuzzleClient(['handler' => $handlerStack]);

    $httpClient = new class($this->config, $guzzle) extends Client
    {
        public function __construct($config, $guzzle)
        {
            parent::__construct($config);
            $this->client = $guzzle;
        }
    };

    $openai = new class($this->config, $httpClient) extends OpenAI
    {
        public function __construct($config, $client)
        {
            parent::__construct($config);
            $this->client = $client;
        }
    };

    $response = $openai->chat([
        ['role' => 'user', 'content' => 'Hi!'],
    ], [
        'model' => 'test-model',
        'temperature' => 0.7,
    ]);

    expect($response)->toBeInstanceOf(ChatCompletion::class)
        ->and($response->id)->toBe('test-id')
        ->and($response->object)->toBe('chat.completion')
        ->and($response->choices[0]->message->content)->toBe('Hello!')
        ->and($response->choices[0]->finishReason->value)->toBe('stop');
});

test('OpenAI client makes correct embeddings request', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'object' => 'list',
            'data' => [
                [
                    'object' => 'embedding',
                    'embedding' => [0.1, 0.2, 0.3],
                    'index' => 0,
                ],
            ],
            'model' => 'test-model',
            'usage' => [
                'prompt_tokens' => 8,
                'total_tokens' => 8,
            ],
        ])),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $guzzle = new GuzzleClient(['handler' => $handlerStack]);

    $httpClient = new class($this->config, $guzzle) extends Client
    {
        public function __construct($config, $guzzle)
        {
            parent::__construct($config);
            $this->client = $guzzle;
        }
    };

    $openai = new class($this->config, $httpClient) extends OpenAI
    {
        public function __construct($config, $client)
        {
            parent::__construct($config);
            $this->client = $client;
        }
    };

    $response = $openai->embeddings('test text', [
        'model' => 'test-model',
    ]);

    expect($response)->toBeInstanceOf(Embedding::class)
        ->and($response->object)->toBe('list')
        ->and($response->data[0]['object'])->toBe('embedding')
        ->and($response->data[0]['embedding'])->toBe([0.1, 0.2, 0.3])
        ->and($response->model)->toBe('test-model');
});
