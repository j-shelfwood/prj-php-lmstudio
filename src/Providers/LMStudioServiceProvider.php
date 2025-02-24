<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Providers;

use Illuminate\Support\ServiceProvider;
use Shelfwood\LMStudio\Commands\Chat;
use Shelfwood\LMStudio\Commands\Models;
use Shelfwood\LMStudio\Commands\ToolResponse;
use Shelfwood\LMStudio\Commands\Tools;
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
                Chat::class,
                Models::class,
                Tools::class,
                ToolResponse::class,
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
