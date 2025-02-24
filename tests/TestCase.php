<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\Http\ApiClient;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\Providers\LMStudioServiceProvider;
use Shelfwood\LMStudio\Support\ChatBuilder;

abstract class TestCase extends BaseTestCase
{
    use MockeryPHPUnitIntegration;

    protected MockHandler $mock;

    protected LMStudio $lmstudio;

    protected ChatBuilder $chatBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock handler and handler stack
        $this->mock = new MockHandler;
        $handlerStack = HandlerStack::create($this->mock);

        // Create dependencies with mocked client
        $apiClient = new ApiClient(['handler' => $handlerStack]);
        $streamingHandler = new StreamingResponseHandler;
        $config = new Config(host: 'localhost', port: 1234, timeout: 30);

        // Create LMStudio instance with dependencies
        $this->lmstudio = new LMStudio(
            config: $config,
            apiClient: $apiClient,
            streamingHandler: $streamingHandler
        );

        $this->chatBuilder = $this->lmstudio->chat();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LMStudioServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('lmstudio', [
            'host' => 'localhost',
            'port' => 1234,
            'timeout' => 30,
            'default_model' => 'test-model',
        ]);
    }
}
