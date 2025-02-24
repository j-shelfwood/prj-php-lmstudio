<?php

declare(strict_types=1);

namespace Tests\Integration;

use Shelfwood\LMStudio\Commands\Chat;
use Shelfwood\LMStudio\Commands\Models;
use Shelfwood\LMStudio\Commands\ToolResponse;
use Shelfwood\LMStudio\Commands\Tools;
use Shelfwood\LMStudio\DTOs\Common\Config as LMStudioConfig;
use Shelfwood\LMStudio\Facades\LMStudio;
use Shelfwood\LMStudio\LMStudio as LMStudioClass;
use Symfony\Component\Console\Application as ConsoleApplication;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    // Set up the console application
    $this->console = new ConsoleApplication;

    // Create LMStudio instance
    $this->lmstudio = new LMStudioClass(
        config: new LMStudioConfig(
            host: 'localhost',
            port: 1234,
            timeout: 30
        )
    );

    // Register commands
    $this->console->add(new Models($this->lmstudio));
    $this->console->add(new Chat($this->lmstudio));
    $this->console->add(new Tools($this->lmstudio));
    $this->console->add(new ToolResponse($this->lmstudio));

    // Bind LMStudio instance
    $this->app->instance('lmstudio', $this->lmstudio);
});

test('it registers the config file', function (): void {
    $config = $this->app['config']->get('lmstudio');

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('host')
        ->and($config)->toHaveKey('port')
        ->and($config)->toHaveKey('timeout')
        ->and($config)->toHaveKey('default_model');
});

test('it registers the facade', function (): void {
    expect($this->app->bound('lmstudio'))->toBeTrue()
        ->and($this->app->make('lmstudio'))->toBeInstanceOf(LMStudioClass::class);
});

test('it registers the commands', function (): void {
    $commands = $this->console->all();

    expect($commands)->toHaveKey('models')
        ->and($commands)->toHaveKey('chat')
        ->and($commands)->toHaveKey('tools')
        ->and($commands)->toHaveKey('tool:response');

    expect($commands['models'])->toBeInstanceOf(Models::class)
        ->and($commands['chat'])->toBeInstanceOf(Chat::class)
        ->and($commands['tools'])->toBeInstanceOf(Tools::class)
        ->and($commands['tool:response'])->toBeInstanceOf(ToolResponse::class);
});
