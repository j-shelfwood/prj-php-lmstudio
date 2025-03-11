<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Builders\StreamBuilder;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;
use Shelfwood\LMStudio\ValueObjects\StreamChunk;
use Shelfwood\LMStudio\ValueObjects\Tool;

beforeEach(function (): void {
    $this->client = Mockery::mock(LMStudioClientInterface::class);
    $this->client->shouldReceive('getApiVersionNamespace')->andReturn('V1');

    $this->history = new ChatHistory([
        Message::system('You are a helpful assistant.'),
        Message::user('Hello, how are you?'),
    ]);

    $this->streamBuilder = new StreamBuilder($this->client);
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

    // Create a generator that yields the chunks
    $mockGenerator = function () {
        yield new StreamChunk(['choices' => [['delta' => ['content' => 'Hello ']]]]);

        yield new StreamChunk(['choices' => [['delta' => ['content' => 'world']]]]);

        yield new StreamChunk(['choices' => [['finish_reason' => 'stop']]]);
    };

    $this->client->shouldReceive('streamChatCompletion')
        ->once()
        ->andReturn($mockGenerator());

    // Configure the stream builder
    $this->streamBuilder
        ->withHistory($this->history)
        ->withModel('qwen2.5-7b-instruct-1m')
        ->withTemperature(0.7)
        ->withMaxTokens(150)
        ->stream(function ($chunk) use (&$contentReceived): void {
            $contentReceived .= $chunk->getContent();
        })
        ->onComplete(function ($content, $toolCalls) use (&$contentReceived): void {
            expect($content)->toBe('Hello world');
            expect($toolCalls)->toBeEmpty();
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

    $this->client->shouldReceive('streamChatCompletion')
        ->once()
        ->andThrow(new \Exception('Test error'));

    $errorReceived = false;
    $this->streamBuilder
        ->withHistory($this->history)
        ->stream(function ($chunk): void {
            // Empty content callback
        })
        ->onError(function ($error) use (&$errorReceived): void {
            $errorReceived = true;
            expect($error->getMessage())->toBe('Test error');
        });

    $this->streamBuilder->execute();

    expect($errorReceived)->toBeTrue();
});
