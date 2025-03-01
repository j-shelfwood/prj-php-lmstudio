<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Enums\Role;
use Shelfwood\LMStudio\Http\Client;
use Shelfwood\LMStudio\OpenAI;
use Shelfwood\LMStudio\Requests\V1\ChatCompletionRequest;
use Shelfwood\LMStudio\Responses\V1\ChatCompletion;
use Shelfwood\LMStudio\Responses\V1\Embedding;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;

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

    // Create a custom HTTP client with the mock handler
    $httpClient = new Client($this->config);
    $httpClient->setGuzzleClient($guzzle);

    // Create the OpenAI client and inject the HTTP client
    $openai = new OpenAI($this->config);
    $openai->setHttpClient($httpClient);

    // The base URL should include the v1 API version
    expect($this->config->getBaseUrl())->toBe('http://example.com');
    $openai->models();

    // Verify that the request was made to the correct endpoint
    $lastRequest = $mock->getLastRequest();
    // Check only the path portion of the URI
    expect((string) $lastRequest->getUri()->getPath())->toBe('v1/models');
});

test('OpenAI client makes correct model request for specific model', function (): void {
    $modelId = 'test-model-id';
    $modelInfo = [
        'id' => $modelId,
        'object' => 'model',
        'created' => 1677858242,
        'owned_by' => 'organization-owner',
        'permission' => [],
        'root' => $modelId,
        'parent' => null,
    ];

    $mock = new MockHandler([
        new Response(200, [], json_encode($modelInfo)),
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

    $response = $openai->model($modelId);
    expect($response)->toBe($modelInfo);

    // Verify that the request was made to the correct endpoint
    $lastRequest = $mock->getLastRequest();
    expect((string) $lastRequest->getUri()->getPath())->toBe('v1/models/'.$modelId);
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
    ];

    // Create a mock client using our trait
    $mockClient = $this->createMockHttpClient([
        'post' => $mockResponse,
    ]);

    // Create the OpenAI client and inject the HTTP client
    $openai = new OpenAI($this->config);
    $openai->setHttpClient($mockClient);

    // Create a request object
    $messages = new ChatHistory([
        new Message(role: Role::USER, content: 'Hello'),
    ]);
    $request = new ChatCompletionRequest($messages, 'gpt-3.5-turbo-0613');

    // Test the new method
    $result = $openai->chatCompletion($request);

    expect($result)->toBeInstanceOf(ChatCompletion::class)
        ->and($result->id)->toBe('chatcmpl-123')
        ->and($result->object)->toBe('chat.completion')
        ->and($result->created)->toBe(1677858242)
        ->and($result->model)->toBe('gpt-3.5-turbo-0613')
        ->and($result->choices)->toHaveCount(1)
        ->and($result->choices[0]->message->content)->toBe('This is a test response');
});

test('it streams chat completions', function (): void {
    $mockGenerator = function () {
        yield ['choices' => [['delta' => ['content' => 'chunk1']]]];

        yield ['choices' => [['delta' => ['content' => 'chunk2']]]];
    };

    // Create a mock client using our trait
    $mockClient = $this->createMockHttpClient([
        'stream' => $mockGenerator(),
    ]);

    // Create the OpenAI client and inject the HTTP client
    $openai = new OpenAI($this->config);
    $openai->setHttpClient($mockClient);

    // Create a request object
    $messages = new ChatHistory([
        new Message(role: Role::USER, content: 'Hello'),
    ]);
    $request = new ChatCompletionRequest($messages, 'gpt-3.5-turbo-0613');
    $request = $request->withStreaming(true);

    // Test the new method
    $result = $openai->streamChatCompletion($request);

    expect($result)->toBeInstanceOf(\Generator::class);

    $chunks = iterator_to_array($result);
    expect($chunks)->toHaveCount(2)
        ->and($chunks[0])->toBe(['choices' => [['delta' => ['content' => 'chunk1']]]])
        ->and($chunks[1])->toBe(['choices' => [['delta' => ['content' => 'chunk2']]]]);
});

test('it accumulates chat content using request objects', function (): void {
    $mockGenerator = function () {
        yield ['choices' => [['delta' => ['content' => 'chunk1']]]];

        yield ['choices' => [['delta' => ['content' => 'chunk2']]]];
    };

    // Create a mock client using our trait
    $mockClient = $this->createMockHttpClient([
        'stream' => $mockGenerator(),
    ]);

    // Create a mock streaming handler using our trait
    $mockStreamingHandler = $this->createMockStreamingHandler([
        'accumulateContent' => 'chunk1chunk2',
    ]);

    // Create the OpenAI client and inject the mocks
    $openai = new OpenAI($this->config);
    $openai->setHttpClient($mockClient);
    $openai->setStreamingHandler($mockStreamingHandler);

    // Create a request object
    $messages = new ChatHistory([
        new Message(role: Role::USER, content: 'Hello'),
    ]);

    // Test with ChatHistory object
    $content = $openai->accumulateChatContent($messages, ['model' => 'gpt-3.5-turbo-0613']);
    expect($content)->toBe('chunk1chunk2');
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
