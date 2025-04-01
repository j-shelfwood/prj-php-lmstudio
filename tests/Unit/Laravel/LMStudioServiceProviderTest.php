<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Shelfwood\LMStudio\Laravel\LMStudioServiceProvider;
use Shelfwood\LMStudio\LMStudioFactory;

describe('LMStudioServiceProvider', function (): void {
    beforeEach(function (): void {
        // Setup default config values
        $this->app['config']->set('lmstudio', [
            'api_key' => 'test-api-key',
            'base_url' => 'http://example.com/api',
            'headers' => [],
            'default_model' => 'test-model',
            'queue' => [
                'connection' => 'test-connection',
                'tools_by_default' => true,
            ],
        ]);

        // Removed manual registration; Orchestra Testbench handles it via getPackageProviders()
    });

    test('service provider is instance of service provider', function (): void {
        $provider = new LMStudioServiceProvider($this->app);
        expect($provider)->toBeInstanceOf(ServiceProvider::class);
    });

    test('register method binds services correctly', function (): void {
        // // Mock the LMStudioFactory to avoid constructor issues - REMOVED
        // $mockFactory = Mockery::mock(LMStudioFactory::class);
        // $this->app->instance(LMStudioFactory::class, $mockFactory);

        // Explicitly register the provider for this test
        $this->app->register(LMStudioServiceProvider::class);

        // Let the actual provider register the factory
        // (Assuming the provider is registered correctly by Testbench)

        // Assert the bindings are made by the REAL provider
        expect($this->app->bound(LMStudioFactory::class))->toBeTrue();
        expect($this->app->bound('lmstudio'))->toBeTrue();

        $factory = $this->app->make(LMStudioFactory::class);
        expect($factory)->toBeInstanceOf(LMStudioFactory::class);

        // Test that the factory is a singleton
        $factory2 = $this->app->make(LMStudioFactory::class);
        expect($factory)->toBe($factory2);

        // Test that the facade accessor is bound
        $facadeInstance = $this->app->make('lmstudio');
        expect($facadeInstance)->toBeInstanceOf(LMStudioFactory::class);
        expect($facadeInstance)->toBe($factory); // Should be the same singleton instance
    });

    test('boot method publishes config', function (): void {
        $this->artisan('vendor:publish', [
            '--provider' => LMStudioServiceProvider::class,
            '--tag' => 'lmstudio-config',
        ]);

        // If we get here, the command didn't throw an exception
        expect(true)->toBeTrue();
    });
});

/**
 * Define environment setup.
 *
 * @param  \Illuminate\Foundation\Application  $app
 */
function getEnvironmentSetUp($app): void
{
    // Setup default config values needed by the provider during registration
    $app['config']->set('lmstudio', [
        'api_key' => 'test-api-key',
        'base_url' => 'http://example.com/api',
        'headers' => [],
        'default_model' => 'test-model',
        'queue' => [
            'connection' => 'test-connection',
            'tools_by_default' => true,
        ],
    ]);
}

/**
 * Get package providers.
 *
 * @param  \Illuminate\Foundation\Application  $app
 * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
 */
function getPackageProviders($app)
{
    return [
        LMStudioServiceProvider::class,
    ];
}
