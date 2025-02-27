<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Providers;

use Illuminate\Support\ServiceProvider;
use Shelfwood\LMStudio\Commands\Sequence;

class LMStudioConsoleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Sequence::class,
            ]);
        }
    }
}
