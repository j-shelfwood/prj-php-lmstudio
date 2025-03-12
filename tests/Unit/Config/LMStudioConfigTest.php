<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Core\Config\LMStudioConfig;

test('config has default values', function (): void {
    $config = new LMStudioConfig;

    expect($config->getBaseUrl())->toBe('http://localhost:1234')
        ->and($config->getApiKey())->toBe('lm-studio')
        ->and($config->getTimeout())->toBe(30)
        ->and($config->getHeaders())->toBe([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer lm-studio',
        ]);
});

test('config can be instantiated with custom values', function (): void {
    $config = new LMStudioConfig(
        baseUrl: 'https://custom.example.com',
        apiKey: 'custom-key',
        timeout: 60,
        headers: ['X-Custom' => 'value']
    );

    expect($config->getBaseUrl())->toBe('https://custom.example.com')
        ->and($config->getApiKey())->toBe('custom-key')
        ->and($config->getTimeout())->toBe(60)
        ->and($config->getHeaders())->toBe([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer custom-key',
            'X-Custom' => 'value',
        ]);
});

test('config is immutable when using with methods', function (): void {
    $original = new LMStudioConfig;

    $withBaseUrl = $original->withBaseUrl('https://new.example.com');
    $withApiKey = $original->withApiKey('new-key');
    $withTimeout = $original->withTimeout(120);
    $withHeaders = $original->withHeaders(['X-New' => 'value']);

    // Original should be unchanged
    expect($original->getBaseUrl())->toBe('http://localhost:1234')
        ->and($original->getApiKey())->toBe('lm-studio')
        ->and($original->getTimeout())->toBe(30)
        ->and($original->getHeaders())->toBe([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer lm-studio',
        ]);

    // New instances should have updated values
    expect($withBaseUrl->getBaseUrl())->toBe('https://new.example.com')
        ->and($withApiKey->getApiKey())->toBe('new-key')
        ->and($withTimeout->getTimeout())->toBe(120)
        ->and($withHeaders->getHeaders())->toBe([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer lm-studio',
            'X-New' => 'value',
        ]);
});

test('base url is trimmed of trailing slashes', function (): void {
    $config = new LMStudioConfig('http://example.com/');
    expect($config->getBaseUrl())->toBe('http://example.com');

    $config = new LMStudioConfig('http://example.com////');
    expect($config->getBaseUrl())->toBe('http://example.com');
});
