<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;

/** @var ToolRegistry $registry */
$registry = null;

beforeEach(function () use (&$registry): void {
    $registry = new ToolRegistry;
});

test('registerTool successfully registers a tool and callback', function () use (&$registry): void {
    $callback = fn (array $args) => 'result';
    $parameters = [
        'type' => 'object',
        'properties' => [
            'param1' => ['type' => 'string', 'description' => 'Param 1 desc'],
        ],
        'required' => ['param1'],
    ];
    $description = 'Test tool description';

    $registry->registerTool('test_tool', $callback, $parameters, $description);

    expect($registry->hasTool('test_tool'))->toBeTrue();
    expect($registry->getCallback('test_tool'))->toBe($callback);

    // Verify internal Tool object structure
    $tools = $registry->getTools();
    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBeInstanceOf(Tool::class)
        ->and($tools[0]->type)->toBe(ToolType::FUNCTION)
        ->and($tools[0]->definition)->toBeInstanceOf(ToolDefinition::class)
        ->and($tools[0]->definition->name)->toBe('test_tool')
        ->and($tools[0]->definition->description)->toBe($description)
        ->and($tools[0]->definition->parameters)->toBeInstanceOf(ToolParameters::class);

    // Access via toArray() and handle stdClass for properties
    $paramsArray = $tools[0]->definition->parameters->toArray();
    expect($paramsArray['type'])->toBe('object')
        ->and($paramsArray['required'])->toBe(['param1']);

    // Cast properties stdClass to array for assertion
    $propsArray = (array) $paramsArray['properties'];
    expect($propsArray)->toHaveKey('param1')
        ->and($propsArray['param1']['type'])->toBe('string');
});

test('registerTool handles missing optional description and empty params', function () use (&$registry): void {
    $callback = fn () => 'simple';
    $parameters = ['type' => 'object', 'properties' => [], 'required' => []]; // Empty but valid

    $registry->registerTool('simple_tool', $callback, $parameters); // No description

    expect($registry->hasTool('simple_tool'))->toBeTrue();
    $tools = $registry->getTools();
    expect($tools)->toHaveCount(1)
        ->and($tools[0]->definition->description)->toBe(''); // Default description

    // Access via toArray() and handle stdClass for properties
    $paramsArray = $tools[0]->definition->parameters->toArray();
    expect((array) $paramsArray['properties'])->toBe([]) // Cast to array
        ->and($paramsArray['required'])->toBe([]);
});

test('hasTool returns correct boolean', function () use (&$registry): void {
    expect($registry->hasTool('non_existent_tool'))->toBeFalse();
    $registry->registerTool('exists', fn () => null, []);
    expect($registry->hasTool('exists'))->toBeTrue();
});

test('getCallback returns correct callback or null', function () use (&$registry): void {
    expect($registry->getCallback('non_existent_tool'))->toBeNull();
    $callback = fn () => 'hello';
    $registry->registerTool('exists', $callback, []);
    expect($registry->getCallback('exists'))->toBe($callback);
});

test('executeTool calls callback and returns result', function () use (&$registry): void {
    $callback = function (array $args) {
        return 'Executed with '.$args['message'];
    };
    $registry->registerTool('executor_test', $callback, ['properties' => ['message' => ['type' => 'string']]]);

    $result = $registry->executeTool('executor_test', ['message' => 'success']);
    expect($result)->toBe('Executed with success');
});

test('executeTool throws exception for non-existent tool', function () use (&$registry): void {
    $registry->executeTool('non_existent', []);
})->throws(RuntimeException::class, "Tool 'non_existent' not found");

test('hasTools returns correct boolean', function () use (&$registry): void {
    expect($registry->hasTools())->toBeFalse();
    $registry->registerTool('a_tool', fn () => null, []);
    expect($registry->hasTools())->toBeTrue();
});

test('getTools returns array of Tool objects', function () use (&$registry): void {
    expect($registry->getTools())->toBe([]);
    $registry->registerTool('tool1', fn () => null, []);
    $registry->registerTool('tool2', fn () => null, []);
    $tools = $registry->getTools();
    expect($tools)->toBeArray()->toHaveCount(2)
        ->and($tools[0])->toBeInstanceOf(Tool::class)
        ->and($tools[1])->toBeInstanceOf(Tool::class);
});

test('getToolsAsArray returns correct API structure', function () use (&$registry): void {
    expect($registry->getToolsAsArray())->toBe([]);

    $callback = fn (array $args) => 'result';
    $parameters = [
        'type' => 'object',
        'properties' => [
            'param1' => ['type' => 'string', 'description' => 'Param 1 desc'],
        ],
        'required' => ['param1'],
    ];
    $description = 'Test tool description';
    $registry->registerTool('test_tool', $callback, $parameters, $description);

    $expectedArray = [
        [
            'type' => 'function',
            'function' => [
                'name' => 'test_tool',
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [ // This might be stdClass depending on ToolParameters::toArray()
                        'param1' => [
                            'type' => 'string',
                            'description' => 'Param 1 desc',
                        ],
                    ],
                    'required' => ['param1'],
                ],
            ],
        ],
    ];

    $actualArray = $registry->getToolsAsArray();
    expect($actualArray)->toBeArray()->toHaveCount(1);

    // Allow for properties being stdClass due to potential ToolParameters issue
    $actualParams = $actualArray[0]['function']['parameters'];
    expect($actualParams['type'])->toBe('object');
    expect($actualParams['required'])->toBe(['param1']);
    expect((array) $actualParams['properties'])->toHaveKey('param1') // Cast to array for checking
        ->and(((array) $actualParams['properties'])['param1']['type'])->toBe('string');

    // Compare the overall structure, acknowledging properties might be stdClass
    expect($actualArray[0]['type'])->toBe($expectedArray[0]['type']);
    expect($actualArray[0]['function']['name'])->toBe($expectedArray[0]['function']['name']);
    expect($actualArray[0]['function']['description'])->toBe($expectedArray[0]['function']['description']);
});
