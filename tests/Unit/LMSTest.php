<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Enums\Role;
use Shelfwood\LMStudio\Http\Client;
use Shelfwood\LMStudio\LMS;
use Shelfwood\LMStudio\Requests\V0\ChatCompletionRequest;
use Shelfwood\LMStudio\Responses\V0\ChatCompletion;
use Shelfwood\LMStudio\Responses\V0\Embedding;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;

beforeEach(function (): void {
    $this->config = new LMStudioConfig(
        baseUrl: 'http://example.com',
        apiKey: 'test-key'
    );
});

test('LMS client uses correct API version', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode(['data' => 'test'])),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $guzzle = new GuzzleClient(['handler' => $handlerStack]);

    // Create a custom HTTP client with the mock handler
    $httpClient = new Client($this->config);
    $httpClient->setGuzzleClient($guzzle);

    // Create the LMS client and inject the HTTP client
    $lms = new LMS($this->config);
    $lms->setHttpClient($httpClient);

    // The base URL should include the v0 API version
    expect($this->config->getBaseUrl())->toBe('http://example.com');
    $lms->models();

    // Verify that the request was made to the correct endpoint
    $lastRequest = $mock->getLastRequest();
    // Check only the path portion of the URI
    expect((string) $lastRequest->getUri()->getPath())->toBe('api/v0/models');
});

test('LMS client makes correct models request', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode(['models' => ['test-model']])),
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

    $lms = new class($this->config, $httpClient) extends LMS
    {
        public function __construct($config, $client)
        {
            parent::__construct($config);
            $this->client = $client;
        }
    };

    $response = $lms->models();
    expect($response)->toBe(['models' => ['test-model']]);
});

test('LMS client makes correct chat request', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1677858242,
            'model' => 'gpt-3.5-turbo',
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'This is a test response',
                    ],
                    'index' => 0,
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
            'stats' => ['some_stat' => 'value'],
            'model_info' => ['some_info' => 'value'],
            'runtime' => ['some_runtime' => 'value'],
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

    $lms = new class($this->config, $httpClient) extends LMS
    {
        public function __construct($config, $client)
        {
            parent::__construct($config);
            $this->client = $client;
        }
    };

    $response = $lms->chat([
        ['role' => 'user', 'content' => 'Hello'],
    ], [
        'model' => 'test-model',
        'temperature' => 0.7,
    ]);

    expect($response)->toBeInstanceOf(ChatCompletion::class)
        ->and($response->id)->toBe('chatcmpl-123')
        ->and($response->object)->toBe('chat.completion')
        ->and($response->choices[0]->message->content)->toBe('This is a test response')
        ->and($response->choices[0]->finishReason->value)->toBe('stop')
        ->and($response->stats)->toBe(['some_stat' => 'value'])
        ->and($response->modelInfo)->toBe(['some_info' => 'value'])
        ->and($response->runtime)->toBe(['some_runtime' => 'value']);
});

test('LMS client makes correct embeddings request', function (): void {
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

    $lms = new class($this->config, $httpClient) extends LMS
    {
        public function __construct($config, $client)
        {
            parent::__construct($config);
            $this->client = $client;
        }
    };

    $response = $lms->embeddings('test text', [
        'model' => 'test-model',
    ]);

    expect($response)->toBeInstanceOf(Embedding::class)
        ->and($response->object)->toBe('list')
        ->and($response->data[0]['object'])->toBe('embedding')
        ->and($response->data[0]['embedding'])->toBe([0.1, 0.2, 0.3])
        ->and($response->model)->toBe('test-model');
});

test('it returns chat completion dto', function (): void {
    $mockResponse = [
        'id' => 'chatcmpl-123',
        'object' => 'chat.completion',
        'created' => 1677858242,
        'model' => 'gpt-3.5-turbo-0613',
        'choices' => [
            [
                'message' => [
                    'role' => 'assistant',
                    'content' => 'This is a test response',
                ],
                'index' => 0,
                'finish_reason' => 'stop',
            ],
        ],
        'usage' => [
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
        ],
        'stats' => ['some_stat' => 'value'],
        'model_info' => ['some_info' => 'value'],
        'runtime' => ['some_runtime' => 'value'],
    ];

    // Create a mock client using our trait
    $mockClient = $this->createMockHttpClient([
        'post' => $mockResponse,
    ]);

    // Create the LMS client and inject the HTTP client
    $lms = new LMS($this->config);
    $lms->setHttpClient($mockClient);

    // Create a request object
    $messages = new ChatHistory([
        new Message(role: Role::USER, content: 'Hello'),
    ]);
    $request = new ChatCompletionRequest($messages, 'gpt-3.5-turbo-0613');

    // Test the new method
    $result = $lms->chatCompletion($request);

    expect($result)->toBeInstanceOf(ChatCompletion::class)
        ->and($result->id)->toBe('chatcmpl-123')
        ->and($result->object)->toBe('chat.completion')
        ->and($result->created)->toBe(1677858242)
        ->and($result->model)->toBe('gpt-3.5-turbo-0613')
        ->and($result->choices)->toHaveCount(1)
        ->and($result->choices[0]->message->content)->toBe('This is a test response')
        ->and($result->stats)->toBe(['some_stat' => 'value'])
        ->and($result->modelInfo)->toBe(['some_info' => 'value'])
        ->and($result->runtime)->toBe(['some_runtime' => 'value']);
});
