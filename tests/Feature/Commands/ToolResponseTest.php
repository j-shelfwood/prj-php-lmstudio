<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\Commands\ToolResponse;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\LMStudio;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class ToolResponseTest extends TestCase
{
    protected MockHandler $mockHandler;

    protected CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($this->mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $lmstudio = new LMStudio(new Config(
            host: 'localhost',
            port: 1234,
            timeout: 30
        ));

        // Replace the client with our mocked version
        $reflection = new \ReflectionClass($lmstudio);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($lmstudio, $client);

        // Create application and register command
        $application = new Application;
        $application->add(new ToolResponse($lmstudio));

        // Get the command tester
        $command = $application->find('tool:response');
        $this->commandTester = new CommandTester($command);
    }

    public function test_it_fails_when_no_model_specified(): void
    {
        $this->commandTester->execute([
            'tool' => 'test',
            'result' => '{}',
        ]);

        expect($this->commandTester->getStatusCode())->toBe(1)
            ->and($this->commandTester->getDisplay())->toContain('No model specified');
    }

    public function test_it_fails_with_invalid_json_result(): void
    {
        $this->commandTester->execute([
            '--model' => 'test-model',
            'tool' => 'test',
            'result' => 'invalid json',
        ]);

        expect($this->commandTester->getStatusCode())->toBe(1)
            ->and($this->commandTester->getDisplay())->toContain('Invalid JSON result');
    }

    public function test_it_can_get_response_for_tool_result(): void
    {
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

        $this->mockHandler->append(new Response(200, [], implode('', $events)));

        $this->commandTester->execute([
            '--model' => 'test-model',
            'tool' => 'get_current_weather',
            'result' => '{"temperature":20,"condition":"sunny"}',
        ]);

        expect($this->commandTester->getStatusCode())->toBe(0)
            ->and($this->commandTester->getDisplay())->toContain('The weather is sunny!');
    }
}
