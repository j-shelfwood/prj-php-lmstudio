<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel;

use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Shelfwood\LMStudio\Laravel\Streaming\LaravelStreamingHandler;
use Shelfwood\LMStudio\Laravel\Tools\QueueableToolExecutionHandler;
use Shelfwood\LMStudio\LMStudioFactory;

class LMStudioServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $configPath = __DIR__.'/config/lmstudio.php';
        $this->mergeConfigFrom($configPath, 'lmstudio');

        // Register the main factory
        $this->app->singleton(LMStudioFactory::class, function ($app) {
            $config = $app['config']['lmstudio'];

            // Resolve logger from container if available
            $logger = $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null;

            // Pass base config and logger to factory constructor using named arguments
            return new LMStudioFactory(
                baseUrl: $config['base_url'] ?? 'http://localhost:1234',
                defaultHeaders: $config['headers'] ?? [],
                apiKey: $config['api_key'] ?? '',
                config: $config, // Pass the whole Laravel config array for potential overrides like default_tools
                logger: $logger // Pass the resolved logger
            );
        });

        // Register the facade accessor
        $this->app->bind('lmstudio', function ($app) {
            return $app->make(LMStudioFactory::class);
        });

        // Register the streaming handler
        $this->app->bind(LaravelStreamingHandler::class, function ($app) {
            return $app->make(LMStudioFactory::class)->createLaravelStreamingHandler();
        });

        // Register the queueable tool execution handler
        $this->app->bind(QueueableToolExecutionHandler::class, function ($app) {
            $handler = new QueueableToolExecutionHandler;

            // Configure the handler with the default queue connection
            $config = $app['config']['lmstudio'];

            if (isset($config['queue']['connection'])) {
                $handler->setQueueConnection($config['queue']['connection']);
            }

            return $handler;
        });

        // Register a factory for queueable conversation builders
        $this->app->bind('lmstudio.queueable_conversation_builder', function ($app) {
            $config = $app['config']['lmstudio'];
            $model = $config['default_model'] ?? 'gpt-3.5-turbo';
            $queueToolsByDefault = $config['queue']['tools_by_default'] ?? false;

            return $app->make(LMStudioFactory::class)
                ->createQueueableConversationBuilder($model, $queueToolsByDefault);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only publish config if we're running in a Laravel app
        if ($this->app->runningInConsole()) {
            $configPath = __DIR__.'/config/lmstudio.php';

            // Use the config_path helper if available, otherwise use a sensible default
            $publishPath = function_exists('config_path')
                ? config_path('lmstudio.php')
                : $this->app->basePath('config/lmstudio.php');

            $this->publishes([
                $configPath => $publishPath,
            ], 'lmstudio-config');

            // Register commands from the core Console directory
            $this->commands([
                \Shelfwood\LMStudio\Console\Command\ChatCommand::class,
                \Shelfwood\LMStudio\Console\Command\SequenceCommand::class,
            ]);
        }
    }
}
