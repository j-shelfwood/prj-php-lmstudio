<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Client\LMS;
use Shelfwood\LMStudio\Api\Client\OpenAI;
use Shelfwood\LMStudio\Api\Contract\LMStudioClientInterface;
use Shelfwood\LMStudio\Core\Config\LMStudioConfig;
use Shelfwood\LMStudio\Core\LMStudio;

test('LMStudio can be instantiated with default config', function (): void {
    $client = new LMStudio;
    expect($client)->toBeInstanceOf(LMStudio::class);
});

test('LMStudio can be instantiated with custom config', function (): void {
    $config = new LMStudioConfig(
        baseUrl: 'https://example.com',
        apiKey: 'test-key',
        timeout: 60,
        headers: ['X-Custom' => 'value']
    );

    $client = new LMStudio($config);
    expect($client)->toBeInstanceOf(LMStudio::class);
});

test('LMStudio provides access to LMS client', function (): void {
    $client = new LMStudio;
    expect($client->lms())->toBeInstanceOf(LMS::class)
        ->and($client->lms())->toBeInstanceOf(LMStudioClientInterface::class);
});

test('LMStudio provides access to OpenAI client', function (): void {
    $client = new LMStudio;
    expect($client->openai())->toBeInstanceOf(OpenAI::class)
        ->and($client->openai())->toBeInstanceOf(LMStudioClientInterface::class);
});

test('LMStudio with methods create new instances', function (): void {
    $original = new LMStudio;

    $withBaseUrl = $original->withBaseUrl('https://new.example.com');
    $withApiKey = $original->withApiKey('new-key');
    $withTimeout = $original->withTimeout(120);
    $withHeaders = $original->withHeaders(['X-New' => 'value']);

    expect($withBaseUrl)->not->toBe($original)
        ->and($withApiKey)->not->toBe($original)
        ->and($withTimeout)->not->toBe($original)
        ->and($withHeaders)->not->toBe($original);
});

test('LMStudio clients are lazily instantiated', function (): void {
    $client = new LMStudio;

    // Create mock clients with type casting
    /** @var \Shelfwood\LMStudio\Api\Client\LMS $mockLms */
    $mockLms = Mockery::mock(LMS::class);
    /** @var \Shelfwood\LMStudio\Api\Client\OpenAI $mockOpenAi */
    $mockOpenAi = Mockery::mock(OpenAI::class);

    // Initially, the clients should not be set
    expect($client->lms())->not->toBe($mockLms)
        ->and($client->openai())->not->toBe($mockOpenAi);

    // Set the mock clients using our adapter classes
    $client->setLmsClient($mockLms);
    $client->setOpenAiClient($mockOpenAi);

    // Now the clients should be the mock instances
    expect($client->lms())->toBe($mockLms)
        ->and($client->openai())->toBe($mockOpenAi);
});

test('LMStudio with methods reset client instances', function (): void {
    $client = new LMStudio;

    // Create and set mock clients with type casting
    /** @var \Shelfwood\LMStudio\Api\Client\LMS $mockLms */
    $mockLms = Mockery::mock(LMS::class);
    /** @var \Shelfwood\LMStudio\Api\Client\OpenAI $mockOpenAi */
    $mockOpenAi = Mockery::mock(OpenAI::class);
    $client->setLmsClient($mockLms);
    $client->setOpenAiClient($mockOpenAi);

    // Create new instance with different config
    $newClient = $client->withBaseUrl('https://new.example.com');

    // The new client should have null clients (they'll be lazily instantiated)
    expect($newClient->lms())->not->toBe($mockLms)
        ->and($newClient->openai())->not->toBe($mockOpenAi);
});
