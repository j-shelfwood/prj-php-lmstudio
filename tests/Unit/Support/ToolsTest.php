<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\DTOs\Tool\ToolCall;
use Shelfwood\LMStudio\DTOs\Tool\ToolFunction;
use Shelfwood\LMStudio\Exceptions\ToolException;
use Shelfwood\LMStudio\Http\ApiClient;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;
use Shelfwood\LMStudio\LMStudio;

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

    $this->chatBuilder = $lmstudio->chat();
});

test('it can register tool handler', function (): void {
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
});

test('it throws exception for unknown tool', function (): void {
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
        ->toThrow(ToolException::class, 'No handler registered for tool: unknown_tool');
});
