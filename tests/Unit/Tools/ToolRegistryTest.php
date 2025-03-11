<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Enums\ToolType;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\FunctionCall;
use Shelfwood\LMStudio\ValueObjects\Tool;
use Shelfwood\LMStudio\ValueObjects\ToolCall;

beforeEach(function (): void {
    $this->registry = new ToolRegistry;

    // Create a test tool
    $this->tool = Tool::function(
        'test_tool',
        'A test tool',
        [
            'param1' => [
                'type' => 'string',
                'description' => 'A test parameter',
                'required' => true,
            ],
            'param2' => [
                'type' => 'number',
                'description' => 'Another test parameter',
                'required' => false,
            ],
        ]
    );

    // Create a handler for the test tool
    $this->handler = function (ToolCall $toolCall) {
        $args = json_decode($toolCall->function->arguments, true);

        return "Tool called with param1: {$args['param1']}";
    };
});

test('it can be instantiated', function (): void {
    expect($this->registry)->toBeInstanceOf(ToolRegistry::class);
});

test('it can register a tool', function (): void {
    $result = $this->registry->register($this->tool, $this->handler);

    expect($result)->toBeInstanceOf(ToolRegistry::class);
    expect($result)->toBe($this->registry); // Fluent interface returns $this
    expect($this->registry->count())->toBe(1);
});

test('it can get registered tools', function (): void {
    $this->registry->register($this->tool, $this->handler);

    $tools = $this->registry->getTools();
    expect($tools)->toBeArray();
    expect($tools)->toHaveCount(1);
    expect($tools)->toContain($this->tool);
});

test('it can check if a tool exists', function (): void {
    $this->registry->register($this->tool, $this->handler);

    expect($this->registry->has('test_tool'))->toBeTrue();
    expect($this->registry->has('nonexistent_tool'))->toBeFalse();
});

test('it can get a specific tool', function (): void {
    $this->registry->register($this->tool, $this->handler);

    $tool = $this->registry->get('test_tool');
    expect($tool)->toBeArray();
    expect($tool)->toHaveKey('tool');
    expect($tool)->toHaveKey('handler');
    expect($tool['tool'])->toBeInstanceOf(Tool::class);
    expect($tool['handler'])->toBeCallable();

    expect($this->registry->get('nonexistent_tool'))->toBeNull();
});

test('it can count registered tools', function (): void {
    expect($this->registry->count())->toBe(0);

    $this->registry->register($this->tool, $this->handler);
    expect($this->registry->count())->toBe(1);

    $anotherTool = Tool::function('another_tool', 'Another test tool', []);
    $this->registry->register($anotherTool, fn () => 'Another result');
    expect($this->registry->count())->toBe(2);
});

test('it can clear all registered tools', function (): void {
    $this->registry->register($this->tool, $this->handler);
    expect($this->registry->count())->toBe(1);

    $result = $this->registry->clear();
    expect($result)->toBeInstanceOf(ToolRegistry::class);
    expect($result)->toBe($this->registry); // Fluent interface returns $this
    expect($this->registry->count())->toBe(0);
});

test('it can execute a registered tool', function (): void {
    // Create a handler that accepts an array of arguments
    $handler = function ($arguments) {
        return "Tool called with param1: {$arguments['param1']}";
    };

    $this->registry->register($this->tool, $handler);

    $toolCall = new ToolCall(
        id: 'call_123',
        type: ToolType::FUNCTION,
        function: new FunctionCall(
            name: 'test_tool',
            arguments: '{"param1":"test value","param2":42}'
        )
    );

    $result = $this->registry->execute($toolCall);
    expect($result)->toBeString();
    expect($result)->toBe('Tool called with param1: test value');
});

test('it throws an exception when executing an unregistered tool', function (): void {
    $toolCall = new ToolCall(
        id: 'call_123',
        type: ToolType::FUNCTION,
        function: new FunctionCall(
            name: 'nonexistent_tool',
            arguments: '{}'
        )
    );

    expect(fn () => $this->registry->execute($toolCall))
        ->toThrow(\InvalidArgumentException::class, 'Tool \'nonexistent_tool\' is not registered');
});
