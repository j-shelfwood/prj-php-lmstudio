<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Provider;

use Illuminate\Support\ServiceProvider;
use Shelfwood\LMStudio\Core\Config\LMStudioConfig;
use Shelfwood\LMStudio\Core\Container\ServiceContainer;
use Shelfwood\LMStudio\Core\LMStudio;

class LMStudioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/lmstudio.php', 'lmstudio');

        $this->app->singleton(LMStudioConfig::class, function ($app) {
            $config = $app['config']['lmstudio'];

            return new LMStudioConfig(
                baseUrl: $config['base_url'] ?? 'http://localhost:1234',
                apiKey: $config['api_key'] ?? 'lm-studio',
                timeout: intval($config['timeout'] ?? 30),
                headers: $config['headers'] ?? [],
                defaultModel: $config['default_model'] ?? null,
                connectTimeout: intval($config['connect_timeout'] ?? 10),
                idleTimeout: intval($config['idle_timeout'] ?? 15),
                maxRetries: intval($config['max_retries'] ?? 3),
                healthCheckEnabled: $config['health_check']['enabled'] ?? true,
                debugConfig: $config['debug'] ?? []
            );
        });

        $this->app->singleton(ServiceContainer::class, function ($app) {
            $container = new ServiceContainer;
            $container->withConfig($app->make(LMStudioConfig::class));

            // If a PSR-3 logger is available in the Laravel container, use it
            if ($app->bound('log')) {
                $container->withLogger($app['log']);
            }

            return $container;
        });

        $this->app->singleton(LMStudio::class, function ($app) {
            return new LMStudio(
                $app->make(LMStudioConfig::class),
                $app->make(ServiceContainer::class)
            );
        });

        $this->app->register(LMStudioConsoleServiceProvider::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../../config/lmstudio.php' => config_path('lmstudio.php'),
            ], 'lmstudio-config');
        }
    }
}
