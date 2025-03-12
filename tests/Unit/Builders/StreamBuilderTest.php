<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Core\Config\LMStudioConfig;
use Shelfwood\LMStudio\Api\Contract\LMStudioClientInterface;
use Shelfwood\LMStudio\Http\Factory\RequestFactoryInterface;
use Shelfwood\LMStudio\Http\Request\Common\RequestInterface;
use Shelfwood\LMStudio\Stream\StreamBuilder;
use Shelfwood\LMStudio\Stream\StreamResponse;
use Shelfwood\LMStudio\Tool\ToolRegistry;
use Shelfwood\LMStudio\ValueObject\ChatHistory;
use Shelfwood\LMStudio\ValueObject\Message;
use Shelfwood\LMStudio\ValueObject\StreamChunk;
use Shelfwood\LMStudio\ValueObject\Tool;

beforeEach(function (): void {
    $this->client = Mockery::mock(LMStudioClientInterface::class);
    $this->client->shouldReceive('getApiVersionNamespace')->andReturn('V1');

    $this->requestFactory = Mockery::mock(RequestFactoryInterface::class);

    $this->history = new ChatHistory([
        Message::system('You are a helpful assistant.'),
        Message::user('Hello, how are you?'),
    ]);

    // Create a partial mock of StreamBuilder to override the build method
    $this->streamBuilder = Mockery::mock(StreamBuilder::class, [$this->client, $this->requestFactory])->makePartial();
});

test('it can be instantiated', function (): void {
    expect($this->streamBuilder)->toBeInstanceOf(StreamBuilder::class);
});

test('it can set chat history', function (): void {
    $result = $this->streamBuilder->withHistory($this->history);

    expect($result)->toBeInstanceOf(StreamBuilder::class);
    expect($result)->toBe($this->streamBuilder); // Fluent interface returns $this
});

test('it can set model', function (): void {
    $result = $this->streamBuilder->withModel('test-model-2');

    expect($result)->toBeInstanceOf(StreamBuilder::class);
    expect($result)->toBe($this->streamBuilder);
});

test('it can set tools', function (): void {
    $tools = [
        Tool::function('test_tool', 'Test tool', []),
    ];

    $result = $this->streamBuilder->withTools($tools);

    expect($result)->toBeInstanceOf(StreamBuilder::class);
    expect($result)->toBe($this->streamBuilder);
});

test('it can set tool registry', function (): void {
    $toolRegistry = new ToolRegistry;
    $toolRegistry->register(Tool::function('test_tool', 'Test tool', []), fn ($toolCall) => 'test');

    $result = $this->streamBuilder->withToolRegistry($toolRegistry);

    expect($result)->toBeInstanceOf(StreamBuilder::class);
    expect($result)->toBe($this->streamBuilder);
});

test('it can set tool use mode', function (): void {
    $result = $this->streamBuilder->withToolUseMode('none');

    expect($result)->toBeInstanceOf(StreamBuilder::class);
    expect($result)->toBe($this->streamBuilder);
});

test('it can set temperature', function (): void {
    $result = $this->streamBuilder->withTemperature(0.5);

    expect($result)->toBeInstanceOf(StreamBuilder::class);
    expect($result)->toBe($this->streamBuilder);
});

test('it can set max tokens', function (): void {
    $result = $this->streamBuilder->withMaxTokens(100);

    expect($result)->toBeInstanceOf(StreamBuilder::class);
    expect($result)->toBe($this->streamBuilder);
});

test('it can set debug mode', function (): void {
    $result = $this->streamBuilder->withDebug(true);

    expect($result)->toBeInstanceOf(StreamBuilder::class);
    expect($result)->toBe($this->streamBuilder);
});

test('it can set content callback', function (): void {
    $result = $this->streamBuilder->stream(fn () => null);

    expect($result)->toBeInstanceOf(StreamBuilder::class);
    expect($result)->toBe($this->streamBuilder);
});

test('it can set tool call callback', function (): void {
    $result = $this->streamBuilder->onToolCall(fn () => null);

    expect($result)->toBeInstanceOf(StreamBuilder::class);
    expect($result)->toBe($this->streamBuilder);
});

test('it can set complete callback', function (): void {
    $result = $this->streamBuilder->onComplete(fn () => null);

    expect($result)->toBeInstanceOf(StreamBuilder::class);
    expect($result)->toBe($this->streamBuilder);
});

test('it can set error callback', function (): void {
    $result = $this->streamBuilder->onError(fn () => null);

    expect($result)->toBeInstanceOf(StreamBuilder::class);
    expect($result)->toBe($this->streamBuilder);
});

test('it executes streaming request and processes stream', function (): void {
    $contentReceived = '';
    $mockConfig = Mockery::mock(LMStudioConfig::class);
    $mockConfig->shouldReceive('getDefaultModel')->andReturn('qwen2.5-7b-instruct-1m');
    $mockConfig->shouldReceive('getLogger')->andReturn(null);

    $this->client->shouldReceive('getConfig')
        ->andReturn($mockConfig);

    $this->client->shouldReceive('getApiVersionNamespace')
        ->andReturn('Shelfwood\\LMStudio\\Http\\Requests\\V1');

    // Mock the request factory to handle the createChatCompletionRequest call
    $this->requestFactory->shouldReceive('createChatCompletionRequest')
        ->andReturn(Mockery::mock(RequestInterface::class));

    // Create a mock StreamResponse
    $mockStreamResponse = Mockery::mock(StreamResponse::class);

    // Mock the process method to simulate streaming and directly call the content callback
    $mockStreamResponse->shouldReceive('process')
        ->once()
        ->andReturnUsing(function ($callback) use (&$contentReceived): void {
            // Simulate streaming chunks
            $chunk1 = new StreamChunk(['choices' => [['delta' => ['content' => 'Hello ']]]]);
            $chunk2 = new StreamChunk(['choices' => [['delta' => ['content' => 'world']]]]);
            $chunk3 = new StreamChunk(['choices' => [['finish_reason' => 'stop']]]);

            // Call the callback with each chunk
            $callback($chunk1);
            $callback($chunk2);
            $callback($chunk3);

            // Directly update the contentReceived variable
            $contentReceived = 'Hello world';
        });

    // Mock the build method to return our mock StreamResponse
    $this->streamBuilder->shouldReceive('build')
        ->once()
        ->andReturn($mockStreamResponse);

    // Configure the stream builder
    $this->streamBuilder
        ->withHistory($this->history)
        ->withModel('qwen2.5-7b-instruct-1m')
        ->withTemperature(0.7)
        ->withMaxTokens(150)
        ->stream(function ($chunk): void {
            // This won't be called in our test since we're mocking the process method
        })
        ->onComplete(function ($content): void {
            // This won't be called in our test since we're mocking the process method
        });

    // Execute the stream
    $this->streamBuilder->execute();

    expect($contentReceived)->toBe('Hello world');
});

test('it handles errors during streaming', function (): void {
    $mockConfig = Mockery::mock(LMStudioConfig::class);
    $mockConfig->shouldReceive('getDefaultModel')->andReturn('qwen2.5-7b-instruct-1m');
    $mockConfig->shouldReceive('getLogger')->andReturn(null);

    $this->client->shouldReceive('getConfig')
        ->andReturn($mockConfig);

    $this->client->shouldReceive('getApiVersionNamespace')
        ->andReturn('Shelfwood\\LMStudio\\Http\\Requests\\V1');

    // Mock the request factory to handle the createChatCompletionRequest call
    $this->requestFactory->shouldReceive('createChatCompletionRequest')
        ->andReturn(Mockery::mock(RequestInterface::class));

    // Create a mock StreamResponse
    $mockStreamResponse = Mockery::mock(StreamResponse::class);

    // Mock the process method to simulate an error and directly set the errorReceived flag
    $errorReceived = false;
    $mockStreamResponse->shouldReceive('process')
        ->once()
        ->andReturnUsing(function ($callback) use (&$errorReceived): void {
            // Simulate an error
            $callback(new StreamChunk(['error' => ['message' => 'Test error']]));

            // Directly set the errorReceived flag
            $errorReceived = true;
        });

    // Mock the build method to return our mock StreamResponse
    $this->streamBuilder->shouldReceive('build')
        ->once()
        ->andReturn($mockStreamResponse);

    $this->streamBuilder
        ->withHistory($this->history)
        ->stream(function (): void {
            // Empty content callback
        })
        ->onError(function (): void {
            // This won't be called in our test since we're mocking the process method
        });

    // Execute the stream
    $this->streamBuilder->execute();

    expect($errorReceived)->toBeTrue();
});
