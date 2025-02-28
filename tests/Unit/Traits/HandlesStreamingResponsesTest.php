<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Http\Client;
use Shelfwood\LMStudio\Traits\HandlesStreamingResponses;

test('it can stream chat completions', function (): void {
    // Create a mock client that returns a generator
    $mockClient = mock(Client::class);
    $mockClient->shouldReceive('stream')
        ->once()
        ->andReturn(createMockGenerator());

    // Create a class that uses the trait
    $handler = new class($mockClient)
    {
        use HandlesStreamingResponses;

        public function __construct(public Client $client) {}
    };

    // Test the legacy streamChat method
    $result = $handler->streamChat([
        ['role' => 'user', 'content' => 'Hello'],
    ], []);

    expect($result)->toBeInstanceOf(\Generator::class);

    // Convert generator to array to test its contents
    $chunks = iterator_to_array($result);
    expect($chunks)->toHaveCount(2)
        ->and($chunks[0])->toBe('test1')
        ->and($chunks[1])->toBe('test2');
});

test('it can stream completions', function (): void {
    // Create a mock client that returns a generator
    $mockClient = mock(Client::class);
    $mockClient->shouldReceive('stream')
        ->once()
        ->andReturn(createMockGenerator());

    // Create a class that uses the trait
    $handler = new class($mockClient)
    {
        use HandlesStreamingResponses;

        public function __construct(public Client $client) {}
    };

    // Test the legacy streamCompletion method
    $result = $handler->streamCompletion('test', []);
    expect($result)->toBeInstanceOf(\Generator::class);

    // Convert generator to array to test its contents
    $chunks = iterator_to_array($result);
    expect($chunks)->toHaveCount(2)
        ->and($chunks[0])->toBe('test1')
        ->and($chunks[1])->toBe('test2');
});

test('it can accumulate content from streaming response', function (): void {
    // Create a mock generator that yields content chunks
    $mockGenerator = function () {
        yield ['choices' => [['delta' => ['content' => 'chunk1']]]];

        yield ['choices' => [['delta' => ['content' => 'chunk2']]]];
    };

    // Create a class that uses the trait
    $handler = new class
    {
        use HandlesStreamingResponses;

        // Expose the protected method for testing
        public function testAccumulateContent(\Generator $stream): string
        {
            return $this->accumulateContent($stream);
        }
    };

    $content = $handler->testAccumulateContent($mockGenerator());
    expect($content)->toBe('chunk1chunk2');
});

test('it can accumulate tool calls from streaming response', function (): void {
    // Create a mock generator that yields tool call chunks
    $mockGenerator = function () {
        yield ['choices' => [['delta' => ['tool_calls' => [
            [
                'index' => 0,
                'id' => 'call_123',
                'type' => 'function',
                'function' => ['name' => 'test_function'],
            ],
        ]]]]];

        yield ['choices' => [['delta' => ['tool_calls' => [
            [
                'index' => 0,
                'function' => ['arguments' => '{"arg1":'],
            ],
        ]]]]];

        yield ['choices' => [['delta' => ['tool_calls' => [
            [
                'index' => 0,
                'function' => ['arguments' => '"value1"}'],
            ],
        ]]]]];
    };

    // Create a class that uses the trait
    $handler = new class
    {
        use HandlesStreamingResponses;

        // Expose the protected method for testing
        public function testAccumulateToolCalls(\Generator $stream): array
        {
            return $this->accumulateToolCalls($stream);
        }
    };

    $toolCalls = $handler->testAccumulateToolCalls($mockGenerator());
    expect($toolCalls)->toHaveCount(1)
        ->and($toolCalls[0]['id'])->toBe('call_123')
        ->and($toolCalls[0]['type'])->toBe('function')
        ->and($toolCalls[0]['function']['name'])->toBe('test_function')
        ->and($toolCalls[0]['function']['arguments'])->toBe('{"arg1":"value1"}');
});

/**
 * Create a mock generator that yields test data.
 */
function createMockGenerator(): \Generator
{
    yield 'test1';

    yield 'test2';
}
