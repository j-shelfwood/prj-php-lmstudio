<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ReflectionClass;
use Shelfwood\LMStudio\Commands\Tools;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\Http\ApiClient;
use Shelfwood\LMStudio\LMStudio;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function (): void {
    $this->mockHandler = new MockHandler;
    $handlerStack = HandlerStack::create($this->mockHandler);
    $client = new Client(['handler' => $handlerStack]);

    $this->lmstudio = new LMStudio(new Config(
        host: 'localhost',
        port: 1234,
        timeout: 30
    ));

    // Replace the client with our mocked version
    $reflection = new ReflectionClass($this->lmstudio);
    $property = $reflection->getProperty('apiClient');
    $property->setAccessible(true);
    $property->setValue($this->lmstudio, new ApiClient(['handler' => $handlerStack]));

    // Create application and register command
    $application = new Application;
    $application->add(new Tools($this->lmstudio));

    // Get the command tester
    $command = $application->find('tools');
    $this->commandTester = new CommandTester($command);
});

test('it fails when no model is specified', function (): void {
    $this->commandTester->execute([]);

    expect($this->commandTester->getStatusCode())->toBe(1)
        ->and($this->commandTester->getDisplay())->toContain('No model specified');
});

test('it can execute a tool call', function (): void {
    // Mock the tool call response
    $toolCallEvents = [
        json_encode([
            'choices' => [[
                'delta' => [
                    'tool_calls' => [[
                        'id' => '123',
                        'type' => 'function',
                        'function' => ['name' => 'get_current_weather'],
                    ]],
                ],
            ]],
        ]).\PHP_EOL,
        json_encode([
            'choices' => [[
                'delta' => [
                    'tool_calls' => [[
                        'function' => ['arguments' => '{"location":"London"}'],
                    ]],
                ],
            ]],
        ]).\PHP_EOL,
        json_encode([
            'choices' => [[
                'delta' => ['content' => 'The weather in London is '],
            ]],
        ]).\PHP_EOL,
        json_encode([
            'choices' => [[
                'delta' => ['content' => 'sunny!'],
            ]],
        ]).\PHP_EOL,
        '[DONE]'.\PHP_EOL,
    ];

    $this->mockHandler->append(new Response(200, [], implode('', $toolCallEvents)));

    // Execute the command with input
    $this->commandTester->setInputs(['What is the weather in London?', 'exit']);
    $this->commandTester->execute(['--model' => 'test-model']);

    $display = $this->commandTester->getDisplay();

    expect($this->commandTester->getStatusCode())->toBe(0)
        ->and($display)->toContain('Testing tool calls with model: test-model')
        ->and($display)->toContain('Assistant: The weather in London is sunny!');
});

test('it handles missing tool handler', function (): void {
    // Mock a tool call for an unregistered handler
    $toolCallEvents = [
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['tool_calls' => [(object) ['id' => '123', 'type' => 'function', 'function' => (object) ['name' => 'unknown_tool']]]]]]])."\n",
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['tool_calls' => [(object) ['function' => (object) ['arguments' => '{"arg":"value"}']]]]]]])."\n",
        "[DONE]\n",
    ];

    $this->mockHandler->append(new Response(200, [], implode('', $toolCallEvents)));

    // Execute the command with input
    $this->commandTester->setInputs(['What is the weather in London?', 'exit']);
    $this->commandTester->execute(['--model' => 'test-model']);

    $display = $this->commandTester->getDisplay();

    expect($this->commandTester->getStatusCode())->toBe(1)
        ->and($display)->toContain('No handler registered for tool: unknown_tool');
});
