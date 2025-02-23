<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\DTOs\Tool\ToolCall;
use Shelfwood\LMStudio\DTOs\Tool\ToolFunction;
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\Support\ChatBuilder;
use Tests\TestCase;

class ToolsTest extends TestCase
{
    protected ChatBuilder $chatBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($mockHandler);
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

        $this->chatBuilder = $lmstudio->chat();
    }

    public function test_it_can_register_tool_handler(): void
    {
        $weatherTool = new ToolFunction(
            name: 'get_current_weather',
            description: 'Get the current weather',
            parameters: [
                'location' => [
                    'type' => 'string',
                    'description' => 'The location to get weather for',
                ],
            ],
            required: ['location']
        );

        $this->chatBuilder->withTools([$weatherTool]);
        $this->chatBuilder->withToolHandler('get_current_weather', fn () => ['temperature' => 20]);

        $toolCall = new ToolCall(
            id: 'test-id',
            type: 'function',
            function: $weatherTool,
            arguments: '{"location":"London"}'
        );

        $reflection = new \ReflectionClass($this->chatBuilder);
        $method = $reflection->getMethod('processToolCall');
        $method->setAccessible(true);

        $result = $method->invoke($this->chatBuilder, $toolCall);
        expect($result)->toBe('{"temperature":20}');
    }

    public function test_it_throws_exception_for_unknown_tool(): void
    {
        $toolCall = new ToolCall(
            id: 'test-id',
            type: 'function',
            function: new ToolFunction(
                name: 'unknown_tool',
                description: 'Unknown tool',
                parameters: [],
                required: []
            )
        );

        $reflection = new \ReflectionClass($this->chatBuilder);
        $method = $reflection->getMethod('processToolCall');
        $method->setAccessible(true);

        expect(fn () => $method->invoke($this->chatBuilder, $toolCall))
            ->toThrow(\Shelfwood\LMStudio\Exceptions\ToolException::class, 'No handler registered for tool: unknown_tool');
    }
}
