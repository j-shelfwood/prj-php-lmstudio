<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Provider;

use Illuminate\Support\ServiceProvider;
use Shelfwood\LMStudio\Console\Command\Chat;
use Shelfwood\LMStudio\Console\Command\Sequence;

class LMStudioConsoleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Sequence::class,
                Chat::class,
            ]);
        }
    }
}
