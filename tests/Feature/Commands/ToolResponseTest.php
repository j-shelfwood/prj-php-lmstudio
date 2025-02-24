<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\Commands\ToolResponse;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\Http\ApiClient;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;
use Shelfwood\LMStudio\LMStudio;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function (): void {
    // Create a mock handler and handler stack
    $this->mock = new MockHandler;
    $handlerStack = HandlerStack::create($this->mock);

    // Create dependencies with mocked client
    $apiClient = new ApiClient(['handler' => $handlerStack]);
    $streamingHandler = new StreamingResponseHandler;
    $config = new Config(host: 'localhost', port: 1234, timeout: 30);

    // Create LMStudio instance with dependencies
    $lmstudio = new LMStudio(
        config: $config,
        apiClient: $apiClient,
        streamingHandler: $streamingHandler
    );

    // Create application and register command
    $application = new Application;
    $application->add(new ToolResponse($lmstudio));

    // Get the command tester
    $command = $application->find('tool:response');
    $this->commandTester = new CommandTester($command);
});

test('it fails when no model specified', function (): void {
    $this->commandTester->execute([
        'tool' => 'test',
        'result' => '{}',
    ]);

    expect($this->commandTester->getStatusCode())->toBe(1)
        ->and($this->commandTester->getDisplay())->toContain('No model specified');
});

test('it fails with invalid json result', function (): void {
    $this->commandTester->execute([
        '--model' => 'test-model',
        'tool' => 'test',
        'result' => 'invalid json',
    ]);

    expect($this->commandTester->getStatusCode())->toBe(1)
        ->and($this->commandTester->getDisplay())->toContain('Invalid JSON result');
});

test('it can get response for tool result', function (): void {
    $events = [
        json_encode([
            'choices' => [[
                'delta' => ['content' => 'The weather is '],
            ]],
        ]).\PHP_EOL,
        json_encode([
            'choices' => [[
                'delta' => ['content' => 'sunny!'],
            ]],
        ]).\PHP_EOL,
        '[DONE]'.\PHP_EOL,
    ];

    $this->mock->append(new Response(200, [], implode('', $events)));

    $this->commandTester->execute([
        '--model' => 'test-model',
        'tool' => 'get_current_weather',
        'result' => '{"temperature":20,"condition":"sunny"}',
    ]);

    expect($this->commandTester->getStatusCode())->toBe(0)
        ->and($this->commandTester->getDisplay())->toContain('The weather is sunny!');
});
