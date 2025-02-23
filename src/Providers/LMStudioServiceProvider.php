<?php

namespace Shelfwood\LMStudio\Providers;

use Illuminate\Support\ServiceProvider;
use Shelfwood\LMStudio\LMStudio;

class LMStudioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/lmstudio.php', 'lmstudio'
        );

        $this->app->singleton('lmstudio', function ($app) {
            return new LMStudio(
                config('lmstudio')
            );
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Shelfwood\LMStudio\Commands\Chat::class,
                \Shelfwood\LMStudio\Commands\Models::class,
                \Shelfwood\LMStudio\Commands\Tools::class,
            ]);
        }
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
