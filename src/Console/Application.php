<?php

namespace Shelfwood\LMStudio\Console;

use LaravelZero\Framework\Application as BaseApplication;
use Shelfwood\LMStudio\Commands\Chat;
use Shelfwood\LMStudio\Commands\Models;
use Shelfwood\LMStudio\Commands\Tools;

class Application extends BaseApplication
{
    protected function commands(): void
    {
        $this->add(new Chat());
        $this->add(new Models());
        $this->add(new Tools());
    }

    protected function bootstrap(): void
    {
        // Load configuration
        $this->loadConfiguration();

        // Register service providers
        $this->registerProviders();
    }

    protected function loadConfiguration(): void
    {
        $this->config->set('lmstudio', require __DIR__.'/../../config/lmstudio.php');
    }

    protected function registerProviders(): void
    {
        $this->register(\Shelfwood\LMStudio\Providers\LMStudioServiceProvider::class);
    }
}