<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\LMS;
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\OpenAI;

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

    // Access reflection to check private properties
    $reflection = new ReflectionClass($client);
    $lmsProperty = $reflection->getProperty('lms');
    $openaiProperty = $reflection->getProperty('openai');

    // Initially null
    expect($lmsProperty->getValue($client))->toBeNull()
        ->and($openaiProperty->getValue($client))->toBeNull();

    // After first access
    $client->lms();
    $client->openai();

    expect($lmsProperty->getValue($client))->toBeInstanceOf(LMS::class)
        ->and($openaiProperty->getValue($client))->toBeInstanceOf(OpenAI::class);
});

test('LMStudio with methods reset client instances', function (): void {
    $client = new LMStudio;

    // Access clients to instantiate them
    $client->lms();
    $client->openai();

    // Create new instance with different config
    $newClient = $client->withBaseUrl('https://new.example.com');

    // Access reflection to check private properties
    $reflection = new ReflectionClass($newClient);
    $lmsProperty = $reflection->getProperty('lms');
    $openaiProperty = $reflection->getProperty('openai');

    expect($lmsProperty->getValue($newClient))->toBeNull()
        ->and($openaiProperty->getValue($newClient))->toBeNull();
});
