<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Orchestra\Testbench\TestCase as Orchestra;
use Shelfwood\LMStudio\Commands\Chat;
use Shelfwood\LMStudio\Commands\Models;
use Shelfwood\LMStudio\Commands\ToolResponse;
use Shelfwood\LMStudio\Commands\Tools;
use Shelfwood\LMStudio\DTOs\Model\ModelInfo;
use Shelfwood\LMStudio\DTOs\Model\ModelList;
use Shelfwood\LMStudio\Facades\LMStudio;
use Shelfwood\LMStudio\LMStudio as LMStudioClass;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;

uses(Orchestra::class)->in(__DIR__);

beforeEach(function (): void {
    // Set up the application
    $this->app['config']->set('app.env', 'testing');
    $this->app['config']->set('lmstudio', [
        'host' => 'localhost',
        'port' => 1234,
        'timeout' => 30,
        'default_model' => 'test-model',
    ]);

    // Set up the console application
    $this->console = new ConsoleApplication;

    // Create a mock LMStudio instance with config
    $config = new \Shelfwood\LMStudio\DTOs\Common\Config(
        host: 'localhost',
        port: 1234,
        timeout: 30,
        defaultModel: 'test-model'
    );

    // Create a mock LMStudio instance
    $this->lmstudio = mock(LMStudioClass::class)
        ->makePartial()
        ->shouldReceive('getConfig')
        ->andReturn($config)
        ->getMock();

    // Register commands
    $this->console->add(new Models($this->lmstudio));
    $this->console->add(new Chat($this->lmstudio));
    $this->console->add(new Tools($this->lmstudio));
    $this->console->add(new ToolResponse($this->lmstudio));

    // Bind LMStudio instance
    $this->app->instance('lmstudio', $this->lmstudio);
});

test('models command lists available models', function (): void {
    // Set up mock response
    $this->lmstudio->shouldReceive('listModels')
        ->once()
        ->andReturn(new ModelList(
            object: 'list',
            data: [
                new ModelInfo(
                    id: 'model-1',
                    object: 'model',
                    created: 1234567890,
                    ownedBy: 'owner'
                ),
            ]
        ));

    // Create command tester
    $command = $this->console->find('models');
    $tester = new CommandTester($command);

    // Execute command
    $tester->execute([]);

    // Verify output
    expect($tester->getDisplay())->toContain('model-1')
        ->and($tester->getStatusCode())->toBe(0);
});

test('chat command starts interactive chat session', function (): void {
    // Set up mock response
    $this->lmstudio->shouldReceive('chat')
        ->andReturn(mock(\Shelfwood\LMStudio\Support\ChatBuilder::class)
            ->makePartial()
            ->shouldReceive('withModel')
            ->andReturnSelf()
            ->shouldReceive('addMessage')
            ->andReturnSelf()
            ->shouldReceive('send')
            ->andReturn('Test response')
            ->getMock()
        );

    // Create command tester
    $command = $this->console->find('chat');
    $tester = new CommandTester($command);

    // Execute command with input
    $tester->setInputs(['Hello', 'exit']);
    $tester->execute(['--model' => 'test-model']);

    // Verify output
    expect($tester->getDisplay())->toContain('Test response')
        ->and($tester->getStatusCode())->toBe(0);
});

test('tools command executes tool calls', function (): void {
    // Set up mock response
    $this->lmstudio->shouldReceive('chat')
        ->andReturn(mock(\Shelfwood\LMStudio\Support\ChatBuilder::class)
            ->makePartial()
            ->shouldReceive('withModel')
            ->andReturnSelf()
            ->shouldReceive('withMessages')
            ->andReturnSelf()
            ->shouldReceive('withTools')
            ->andReturnSelf()
            ->shouldReceive('withToolHandler')
            ->andReturnSelf()
            ->shouldReceive('stream')
            ->andReturnSelf()
            ->shouldReceive('send')
            ->andReturn([
                new \Shelfwood\LMStudio\DTOs\Chat\Message(
                    role: \Shelfwood\LMStudio\DTOs\Chat\Role::ASSISTANT,
                    content: 'Let me check the weather for London.'
                ),
                new \Shelfwood\LMStudio\DTOs\Tool\ToolCall(
                    id: 'call_123',
                    type: 'function',
                    function: new \Shelfwood\LMStudio\DTOs\Tool\ToolFunction(
                        name: 'get_current_weather',
                        description: 'Get the current weather in a location',
                        parameters: ['location' => ['type' => 'string', 'description' => 'The location to get weather for']],
                        required: ['location']
                    )
                ),
                new \Shelfwood\LMStudio\DTOs\Chat\Message(
                    role: \Shelfwood\LMStudio\DTOs\Chat\Role::TOOL,
                    content: '{"temperature":20,"condition":"sunny","location":"London"}'
                ),
            ])
            ->getMock()
        );

    // Create command tester
    $command = $this->console->find('tools');
    $tester = new CommandTester($command);

    // Execute command with input
    $tester->setInputs(['What\'s the weather in London?', 'exit']);
    $tester->execute(['--model' => 'test-model']);

    // Verify output
    expect($tester->getDisplay())->toContain('Testing tool calls with model: test-model')
        ->and($tester->getDisplay())->toContain('Let me check the weather for London')
        ->and($tester->getDisplay())->toContain('Tool response:')
        ->and($tester->getDisplay())->toContain('London')
        ->and($tester->getStatusCode())->toBe(0);
});
