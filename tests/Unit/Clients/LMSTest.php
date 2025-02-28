<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Enums\Role;
use Shelfwood\LMStudio\Http\Client;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;
use Shelfwood\LMStudio\LMS;
use Shelfwood\LMStudio\Requests\V0\ChatCompletionRequest;
use Shelfwood\LMStudio\Requests\V0\EmbeddingRequest;
use Shelfwood\LMStudio\Requests\V0\TextCompletionRequest;
use Shelfwood\LMStudio\Responses\V0\ChatCompletion;
use Shelfwood\LMStudio\Responses\V0\Embedding;
use Shelfwood\LMStudio\Responses\V0\TextCompletion;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;

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

    /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('post')
        ->once()
        ->andReturn($mockResponse);

    // Create a mock config
    $mockConfig = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create LMS instance with mocked dependencies
    $lms = new LMS($mockConfig);

    // Set the client using the setter method instead of reflection
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

test('it returns text completion dto', function (): void {
    $mockResponse = [
        'id' => 'cmpl-123',
        'object' => 'text_completion',
        'created' => 1677858242,
        'model' => 'text-davinci-003',
        'choices' => [
            [
                'text' => 'This is a test completion',
                'index' => 0,
                'finish_reason' => 'stop',
            ],
        ],
        'usage' => [
            'prompt_tokens' => 5,
            'completion_tokens' => 10,
            'total_tokens' => 15,
        ],
        'stats' => ['some_stat' => 'value'],
        'model_info' => ['some_info' => 'value'],
        'runtime' => ['some_runtime' => 'value'],
    ];

        /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('post')
        ->once()
        ->andReturn($mockResponse);

    // Create a mock config
    $mockConfig = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create LMS instance with mocked dependencies
    $lms = new LMS($mockConfig);

    // Set the client using the setter method instead of reflection
    $lms->setHttpClient($mockClient);

    // Create a request object
    $request = new TextCompletionRequest('Test prompt', 'text-davinci-003');

    // Test the new method
    $result = $lms->textCompletion($request);

    expect($result)->toBeInstanceOf(TextCompletion::class)
        ->and($result->id)->toBe('cmpl-123')
        ->and($result->object)->toBe('text_completion')
        ->and($result->created)->toBe(1677858242)
        ->and($result->model)->toBe('text-davinci-003')
        ->and($result->choices)->toHaveCount(1)
        ->and($result->choices[0]['text'])->toBe('This is a test completion')
        ->and($result->stats)->toBe(['some_stat' => 'value'])
        ->and($result->modelInfo)->toBe(['some_info' => 'value'])
        ->and($result->runtime)->toBe(['some_runtime' => 'value']);
});

test('it returns embedding dto', function (): void {
    $mockResponse = [
        'object' => 'list',
        'data' => [
            [
                'object' => 'embedding',
                'embedding' => [0.1, 0.2, 0.3],
                'index' => 0,
            ],
        ],
        'model' => 'text-embedding-ada-002',
        'usage' => [
            'prompt_tokens' => 8,
            'total_tokens' => 8,
        ],
    ];

    /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('post')
        ->once()
        ->andReturn($mockResponse);

    // Create a mock config
    $mockConfig = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create LMS instance with mocked dependencies
    $lms = new LMS($mockConfig);

    // Set the client using the setter method instead of reflection
    $lms->setHttpClient($mockClient);

    // Create a request object
    $request = new EmbeddingRequest('Test text', 'text-embedding-ada-002');

    // Test the new method
    $result = $lms->createEmbeddings($request);

    expect($result)->toBeInstanceOf(Embedding::class)
        ->and($result->object)->toBe('list')
        ->and($result->data)->toHaveCount(1)
        ->and($result->data[0]['object'])->toBe('embedding')
        ->and($result->data[0]['embedding'])->toBe([0.1, 0.2, 0.3])
        ->and($result->model)->toBe('text-embedding-ada-002');
});

test('it streams chat completions', function (): void {
    $mockGenerator = function () {
        yield ['choices' => [['delta' => ['content' => 'chunk1']]]];

        yield ['choices' => [['delta' => ['content' => 'chunk2']]]];
    };

    /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('stream')
        ->once()
        ->andReturn($mockGenerator());

    // Create a mock config
    $mockConfig = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create LMS instance with mocked dependencies
    $lms = new LMS($mockConfig);

    // Set the client using the setter method instead of reflection
    $lms->setHttpClient($mockClient);

    // Create a request object
    $messages = new ChatHistory([
        new Message(role: Role::USER, content: 'Hello'),
    ]);
    $request = new ChatCompletionRequest($messages, 'gpt-3.5-turbo-0613');
    $request = $request->withStreaming(true);

    // Test the new method
    $result = $lms->streamChatCompletion($request);

    expect($result)->toBeInstanceOf(\Generator::class);

    $chunks = iterator_to_array($result);
    expect($chunks)->toHaveCount(2)
        ->and($chunks[0])->toBe(['choices' => [['delta' => ['content' => 'chunk1']]]])
        ->and($chunks[1])->toBe(['choices' => [['delta' => ['content' => 'chunk2']]]]);
});

test('it streams completions', function (): void {
    $mockGenerator = function () {
        yield ['choices' => [['text' => 'chunk1']]];

        yield ['choices' => [['text' => 'chunk2']]];
    };

    /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('stream')
        ->once()
        ->andReturn($mockGenerator());

    // Create a mock config
    $mockConfig = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create LMS instance with mocked dependencies
    $lms = new LMS($mockConfig);

    // Set the client using the setter method instead of reflection
    $lms->setHttpClient($mockClient);

    // Create a request object
    $request = new TextCompletionRequest('Test prompt', 'text-davinci-003');
    $request = $request->withStreaming(true);

    // Test the new method
    $result = $lms->streamTextCompletion($request);

    expect($result)->toBeInstanceOf(\Generator::class);

    $chunks = iterator_to_array($result);
    expect($chunks)->toHaveCount(2)
        ->and($chunks[0])->toBe(['choices' => [['text' => 'chunk1']]])
        ->and($chunks[1])->toBe(['choices' => [['text' => 'chunk2']]]);
});

// Add tests for the legacy methods that now use the new request objects
test('it uses new request objects in legacy chat method', function (): void {
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

    /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('post')
        ->once()
        ->andReturn($mockResponse);

    // Create a mock config
    $mockConfig = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create LMS instance with mocked dependencies
    $lms = new LMS($mockConfig);

    // Set the client using the setter method instead of reflection
    $lms->setHttpClient($mockClient);

    // Test the legacy method
    $result = $lms->chat([
        ['role' => 'user', 'content' => 'Hello'],
    ], ['model' => 'gpt-3.5-turbo-0613']);

    expect($result)->toBeInstanceOf(ChatCompletion::class);
});

test('it accumulates chat content using request objects', function (): void {
    $mockGenerator = function () {
        yield ['choices' => [['delta' => ['content' => 'chunk1']]]];

        yield ['choices' => [['delta' => ['content' => 'chunk2']]]];
    };

    /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('stream')
        ->once()
        ->andReturn($mockGenerator());

    /** @var \Shelfwood\LMStudio\Http\StreamingResponseHandler|Mockery\MockInterface $mockStreamingHandler */
    $mockStreamingHandler = Mockery::mock(StreamingResponseHandler::class);
    $mockStreamingHandler->shouldReceive('accumulateContent')
        ->once()
        ->andReturn('chunk1chunk2');

    // Create a mock config
    $mockConfig = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create LMS instance with mocked dependencies
    $lms = new LMS($mockConfig);

    // Set the client and streaming handler using setter methods
    $lms->setHttpClient($mockClient);
    $lms->setStreamingHandler($mockStreamingHandler);

    // Create a request object
    $messages = new ChatHistory([
        new Message(role: Role::USER, content: 'Hello'),
    ]);

    // Test with ChatHistory object
    $content = $lms->accumulateChatContent($messages, ['model' => 'gpt-3.5-turbo-0613']);
    expect($content)->toBe('chunk1chunk2');
});
