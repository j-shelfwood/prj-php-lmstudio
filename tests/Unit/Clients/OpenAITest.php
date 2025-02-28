<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Enums\Role;
use Shelfwood\LMStudio\Http\Client;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;
use Shelfwood\LMStudio\OpenAI;
use Shelfwood\LMStudio\Requests\V1\ChatCompletionRequest;
use Shelfwood\LMStudio\Requests\V1\EmbeddingRequest;
use Shelfwood\LMStudio\Requests\V1\TextCompletionRequest;
use Shelfwood\LMStudio\Responses\V1\ChatCompletion;
use Shelfwood\LMStudio\Responses\V1\Embedding;
use Shelfwood\LMStudio\Responses\V1\TextCompletion;
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
    ];

    // Create a real config
    $config = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create OpenAI instance
    $openai = new OpenAI($config);

    /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('post')
        ->once()
        ->andReturn($mockResponse);

    // Set the client using the setter method instead of reflection
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
    ];

    // Create a real config
    $config = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create OpenAI instance
    $openai = new OpenAI($config);

    /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('post')
        ->once()
        ->andReturn($mockResponse);

    // Set the client using the setter method instead of reflection
    $openai->setHttpClient($mockClient);

    // Create a request object
    $request = new TextCompletionRequest('Test prompt', 'text-davinci-003');

    // Test the new method
    $result = $openai->textCompletion($request);

    expect($result)->toBeInstanceOf(TextCompletion::class)
        ->and($result->id)->toBe('cmpl-123')
        ->and($result->object)->toBe('text_completion')
        ->and($result->created)->toBe(1677858242)
        ->and($result->model)->toBe('text-davinci-003')
        ->and($result->choices)->toHaveCount(1)
        ->and($result->choices[0]['text'])->toBe('This is a test completion');
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

    // Create a real config
    $config = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create OpenAI instance
    $openai = new OpenAI($config);

    /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('post')
        ->once()
        ->andReturn($mockResponse);

    // Set the client using the setter method instead of reflection
    $openai->setHttpClient($mockClient);

    // Create a request object
    $request = new EmbeddingRequest('Test text', 'text-embedding-ada-002');

    // Test the new method
    $result = $openai->createEmbeddings($request);

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

    // Create a real config
    $config = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create OpenAI instance
    $openai = new OpenAI($config);

    /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('stream')
        ->once()
        ->andReturn($mockGenerator());

    // Set the client using the setter method instead of reflection
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

test('it streams completions', function (): void {
    $mockGenerator = function () {
        yield ['choices' => [['text' => 'chunk1']]];

        yield ['choices' => [['text' => 'chunk2']]];
    };

    // Create a real config
    $config = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create OpenAI instance
    $openai = new OpenAI($config);

    /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('stream')
        ->once()
        ->andReturn($mockGenerator());

    // Set the client using the setter method instead of reflection
    $openai->setHttpClient($mockClient);

    // Create a request object
    $request = new TextCompletionRequest('Test prompt', 'text-davinci-003');
    $request = $request->withStreaming(true);

    // Test the new method
    $result = $openai->streamTextCompletion($request);

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

    // Create a real config
    $config = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create OpenAI instance
    $openai = new OpenAI($config);

    /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('post')
        ->once()
        ->andReturn($mockResponse);

    // Set the client using the setter method instead of reflection
    $openai->setHttpClient($mockClient);

    // Test the legacy method
    $result = $openai->chat([
        ['role' => 'user', 'content' => 'Hello'],
    ], ['model' => 'gpt-3.5-turbo-0613']);

    expect($result)->toBeInstanceOf(ChatCompletion::class);
});

test('it accumulates chat content using request objects', function (): void {
    $mockGenerator = function () {
        yield ['choices' => [['delta' => ['content' => 'chunk1']]]];

        yield ['choices' => [['delta' => ['content' => 'chunk2']]]];
    };

    // Create a real config
    $config = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create OpenAI instance
    $openai = new OpenAI($config);

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

    // Set the properties with our mocks
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
