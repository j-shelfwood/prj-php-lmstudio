<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\Commands\ToolResponse;
use Shelfwood\LMStudio\LMStudio;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->mockHandler = new MockHandler;
    $handlerStack = HandlerStack::create($this->mockHandler);
    $client = new Client(['handler' => $handlerStack]);

    $this->lmstudio = new LMStudio(
        host: 'localhost',
        port: 1234,
        timeout: 30
    );

    // Replace the client with our mocked version
    $reflection = new ReflectionClass($this->lmstudio);
    $property = $reflection->getProperty('client');
    $property->setAccessible(true);
    $property->setValue($this->lmstudio, $client);

    // Create application and register command
    $application = new Application;
    $application->add(new ToolResponse($this->lmstudio));

    // Get the command tester
    $command = $application->find('tool:response');
    $this->commandTester = new CommandTester($command);
});

test('it fails when no model is specified', function () {
    $this->commandTester->execute([
        'tool' => 'get_current_weather',
        'result' => '{"temperature":20,"condition":"sunny","location":"London"}',
    ]);

    expect($this->commandTester->getStatusCode())->toBe(1)
        ->and($this->commandTester->getDisplay())->toContain('No model specified');
});

test('it fails with invalid JSON result', function () {
    $this->commandTester->execute([
        '--model' => 'test-model',
        'tool' => 'get_current_weather',
        'result' => 'invalid json',
    ]);

    expect($this->commandTester->getStatusCode())->toBe(1)
        ->and($this->commandTester->getDisplay())->toContain('Invalid JSON result');
});

test('it can get response for tool result', function () {
    // Mock the response
    $events = [
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['content' => 'The weather in London is sunny']]]])."\n",
        json_encode((object) ['choices' => [(object) ['delta' => (object) ['content' => ' with a temperature of 20°C']]]])."\n",
        "[DONE]\n",
    ];

    $this->mockHandler->append(new Response(200, [], implode('', $events)));

    $this->commandTester->execute([
        '--model' => 'test-model',
        'tool' => 'get_current_weather',
        'result' => '{"temperature":20,"condition":"sunny","location":"London"}',
    ]);

    $display = $this->commandTester->getDisplay();

    expect($this->commandTester->getStatusCode())->toBe(0)
        ->and($display)->toContain('The weather in London is sunny')
        ->and($display)->toContain('with a temperature of 20°C');
});
