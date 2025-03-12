<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Container\NotFoundException;
use Shelfwood\LMStudio\Container\ServiceContainer;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Http\Client;
use Shelfwood\LMStudio\LMS;
use Shelfwood\LMStudio\OpenAI;

describe('ServiceContainer', function (): void {
    it('can be instantiated', function (): void {
        $container = new ServiceContainer;
        expect($container)->toBeInstanceOf(ServiceContainer::class);
    });

    it('registers default services', function (): void {
        $container = new ServiceContainer;

        expect($container->has(LMStudioConfig::class))->toBeTrue();
        expect($container->has(Client::class))->toBeTrue();
        expect($container->has(LMS::class))->toBeTrue();
        expect($container->has(OpenAI::class))->toBeTrue();
        expect($container->has(LMStudioClientInterface::class))->toBeTrue();
    });

    it('can get a service', function (): void {
        $container = new ServiceContainer;

        $config = $container->get(LMStudioConfig::class);
        expect($config)->toBeInstanceOf(LMStudioConfig::class);

        $client = $container->get(Client::class);
        expect($client)->toBeInstanceOf(Client::class);

        $lms = $container->get(LMS::class);
        expect($lms)->toBeInstanceOf(LMS::class);

        $openai = $container->get(OpenAI::class);
        expect($openai)->toBeInstanceOf(OpenAI::class);

        $defaultClient = $container->get(LMStudioClientInterface::class);
        expect($defaultClient)->toBeInstanceOf(LMStudioClientInterface::class);
    });

    it('throws an exception for non-existent services', function (): void {
        $container = new ServiceContainer;

        expect(fn () => $container->get('NonExistentService'))->toThrow(NotFoundException::class);
    });

    it('can bind a service', function (): void {
        $container = new ServiceContainer;

        $container->bind('TestService', fn () => 'test');
        expect($container->has('TestService'))->toBeTrue();
        expect($container->get('TestService'))->toBe('test');
    });

    it('can set an instance', function (): void {
        $container = new ServiceContainer;

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $container->instance(LoggerInterface::class, $mockLogger);

        expect($container->has(LoggerInterface::class))->toBeTrue();
        expect($container->get(LoggerInterface::class))->toBe($mockLogger);
    });

    it('can set a custom config', function (): void {
        $container = new ServiceContainer;

        $config = new LMStudioConfig(baseUrl: 'https://custom-url.com');
        $container->withConfig($config);

        expect($container->get(LMStudioConfig::class))->toBe($config);

        // Check that clients are reset
        $client = $container->get(Client::class);
        expect($client)->toBeInstanceOf(Client::class);
    });

    it('can set a logger', function (): void {
        $container = new ServiceContainer;

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $container->withLogger($mockLogger);

        expect($container->get(LoggerInterface::class))->toBe($mockLogger);
    });

    it('can switch between client implementations', function (): void {
        $container = new ServiceContainer;

        // Default is LMS
        $defaultClient = $container->get(LMStudioClientInterface::class);
        expect($defaultClient)->toBeInstanceOf(LMS::class);

        // Switch to OpenAI
        $container->useClient(OpenAI::class);
        $openaiClient = $container->get(LMStudioClientInterface::class);
        expect($openaiClient)->toBeInstanceOf(OpenAI::class);

        // Switch back to LMS
        $container->useClient(LMS::class);
        $lmsClient = $container->get(LMStudioClientInterface::class);
        expect($lmsClient)->toBeInstanceOf(LMS::class);
    });

    it('throws an exception for invalid client class', function (): void {
        $container = new ServiceContainer;

        expect(fn () => $container->useClient('InvalidClass'))->toThrow(\InvalidArgumentException::class);
    });
});
