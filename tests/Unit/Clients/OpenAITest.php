<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Core\Config\LMStudioConfig;
use Shelfwood\LMStudio\Enum\Role;
use Shelfwood\LMStudio\Http\Client;
use Shelfwood\LMStudio\Http\Request\V1\ChatCompletionRequest;
use Shelfwood\LMStudio\Http\Request\V1\EmbeddingRequest;
use Shelfwood\LMStudio\Http\Request\V1\TextCompletionRequest;
use Shelfwood\LMStudio\Http\Response\V1\ChatCompletion;
use Shelfwood\LMStudio\Http\Response\V1\Embedding;
use Shelfwood\LMStudio\Http\Response\V1\TextCompletion;
use Shelfwood\LMStudio\Api\Client\OpenAI;
use Shelfwood\LMStudio\ValueObject\ChatHistory;
use Shelfwood\LMStudio\ValueObject\Message;

test('it returns chat completion dto', function (): void {
    $mockResponse = [
        'id' => 'chatcmpl-123',
        'object' => 'chat.completion',
        'created' => 1677858242,
        'model' => 'qwen2.5-7b-instruct-1m-0613',
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
    $mockClient->shouldReceive('checkHealth')
        ->andReturn(true);

    // Set the client using the setter method instead of reflection
    $openai->setHttpClient($mockClient);

    // Create a request object
    $messages = new ChatHistory([
        new Message(role: Role::USER, content: 'Hello'),
    ]);
    $request = new ChatCompletionRequest($messages, 'qwen2.5-7b-instruct-1m-0613');

    // Test the new method
    $result = $openai->chatCompletion($request);

    expect($result)->toBeInstanceOf(ChatCompletion::class)
        ->and($result->id)->toBe('chatcmpl-123')
        ->and($result->object)->toBe('chat.completion')
        ->and($result->created)->toBe(1677858242)
        ->and($result->model)->toBe('qwen2.5-7b-instruct-1m-0613')
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
    $mockClient->shouldReceive('checkHealth')
        ->andReturn(true);

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
    $mockClient->shouldReceive('checkHealth')
        ->andReturn(true);

    // Set the client using the setter method
    $openai->setHttpClient($mockClient);

    // Create a request object
    $request = new EmbeddingRequest('Test text', 'text-embedding-ada-002');

    // Test the new method
    $result = $openai->createEmbeddings($request);

    expect($result)->toBeInstanceOf(Embedding::class)
        ->and($result->object)->toBe('list')
        ->and($result->data)->toHaveCount(1)
        ->and($result->data[0]['embedding'])->toBe([0.1, 0.2, 0.3])
        ->and($result->model)->toBe('text-embedding-ada-002');
});

test('it streams chat completions', function (): void {
    // Create a real config
    $config = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create OpenAI instance
    $openai = new OpenAI($config);

    /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);

    // Create a generator that yields the chunks
    $mockGenerator = function () {
        yield ['choices' => [['delta' => ['content' => 'Hello']]]];

        yield ['choices' => [['delta' => ['content' => ' world']]]];

        yield ['choices' => [['finish_reason' => 'stop']]];
    };

    $mockClient->shouldReceive('stream')
        ->once()
        ->andReturn($mockGenerator());
    $mockClient->shouldReceive('checkHealth')
        ->andReturn(true);

    // Set the client using the setter method
    $openai->setHttpClient($mockClient);

    // Create a request object
    $messages = new ChatHistory([
        new Message(role: Role::USER, content: 'Hello'),
    ]);
    $request = new ChatCompletionRequest($messages, 'qwen2.5-7b-instruct-1m');
    $request = $request->withStreaming(true);

    // Test the streaming method
    $result = $openai->streamChatCompletion($request);

    expect($result)->toBeInstanceOf(\Generator::class);

    $chunks = iterator_to_array($result);
    expect($chunks)->toHaveCount(3)
        ->and($chunks[0])->toBe(['choices' => [['delta' => ['content' => 'Hello']]]])
        ->and($chunks[1])->toBe(['choices' => [['delta' => ['content' => ' world']]]])
        ->and($chunks[2])->toBe(['choices' => [['finish_reason' => 'stop']]]);
});

test('it streams completions', function (): void {
    // Create a real config
    $config = new LMStudioConfig(
        baseUrl: 'http://localhost:1234',
        apiKey: 'test-key'
    );

    // Create OpenAI instance
    $openai = new OpenAI($config);

    /** @var \Shelfwood\LMStudio\Http\Client|Mockery\MockInterface $mockClient */
    $mockClient = Mockery::mock(Client::class);

    // Create a generator that yields the chunks
    $mockGenerator = function () {
        yield ['choices' => [['text' => 'Hello']]];

        yield ['choices' => [['text' => ' world']]];

        yield ['choices' => [['finish_reason' => 'stop']]];
    };

    $mockClient->shouldReceive('stream')
        ->once()
        ->andReturn($mockGenerator());
    $mockClient->shouldReceive('checkHealth')
        ->andReturn(true);

    // Set the client using the setter method
    $openai->setHttpClient($mockClient);

    // Create a request object
    $request = new TextCompletionRequest('Test prompt', 'text-davinci-003');
    $request = $request->withStreaming(true);

    // Test the new method
    $result = $openai->streamTextCompletion($request);

    expect($result)->toBeInstanceOf(\Generator::class);

    $chunks = iterator_to_array($result);
    expect($chunks)->toHaveCount(3)
        ->and($chunks[0])->toBe(['choices' => [['text' => 'Hello']]])
        ->and($chunks[1])->toBe(['choices' => [['text' => ' world']]])
        ->and($chunks[2])->toBe(['choices' => [['finish_reason' => 'stop']]]);
});
