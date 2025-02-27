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

    // Test the streamChat method
    $result = $handler->streamChat([], []);
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

    // Test the streamCompletion method
    $result = $handler->streamCompletion('test', []);
    expect($result)->toBeInstanceOf(\Generator::class);

    // Convert generator to array to test its contents
    $chunks = iterator_to_array($result);
    expect($chunks)->toHaveCount(2)
        ->and($chunks[0])->toBe('test1')
        ->and($chunks[1])->toBe('test2');
});

/**
 * Create a mock generator that yields test data.
 */
function createMockGenerator(): \Generator
{
    yield 'test1';

    yield 'test2';
}
