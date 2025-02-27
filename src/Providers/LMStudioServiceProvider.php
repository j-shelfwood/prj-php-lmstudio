<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Providers;

use Illuminate\Support\ServiceProvider;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\LMStudio;

class LMStudioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/lmstudio.php', 'lmstudio');

        $this->app->singleton(LMStudioConfig::class, function ($app) {
            $config = $app['config']['lmstudio'];

            return new LMStudioConfig(
                baseUrl: $config['base_url'] ?? 'http://localhost:1234',
                apiKey: $config['api_key'] ?? 'lm-studio',
                timeout: $config['timeout'] ?? 30,
                headers: $config['headers'] ?? [],
            );
        });

        $this->app->singleton(LMStudio::class, function ($app) {
            return new LMStudio($app->make(LMStudioConfig::class));
        });

        $this->app->register(LMStudioConsoleServiceProvider::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/lmstudio.php' => config_path('lmstudio.php'),
            ], 'lmstudio-config');
        }
    }
}
