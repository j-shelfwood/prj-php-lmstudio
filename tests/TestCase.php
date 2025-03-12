<?php

declare(strict_types=1);

namespace Tests;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Shelfwood\LMStudio\Core\Provider\LMStudioServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LMStudioServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('lmstudio.base_url', 'http://localhost:1234');
        $app['config']->set('lmstudio.api_key', 'test-key');
        $app['config']->set('lmstudio.timeout', 30);
    }
}
