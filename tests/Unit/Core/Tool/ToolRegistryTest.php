<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;

describe('ToolRegistry', function (): void {
    beforeEach(function () use (&$registry): void {
        $this->registry = new ToolRegistry;
    });

    test('registerTool successfully registers a tool and callback', function (): void {
        $callback = fn (array $args) => 'result';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'param1' => ['type' => 'string', 'description' => 'Param 1 desc'],
            ],
            'required' => ['param1'],
        ];
        $description = 'Test tool description';

        $this->registry->registerTool('test_tool', $callback, $parameterSchema, $description);

        expect($this->registry->hasTool('test_tool'))->toBeTrue();
        expect($this->registry->getCallback('test_tool'))->toBe($callback);

        // Verify internal Tool object structure (retrieved via getTools)
        $tools = $this->registry->getTools();
        expect($tools)->toHaveCount(1);
        $toolObject = $tools[0];
        expect($toolObject)->toBeInstanceOf(Tool::class)
            ->and($toolObject->type)->toBe(ToolType::FUNCTION);

        $definition = $toolObject->definition;
        expect($definition)->toBeInstanceOf(ToolDefinition::class)
            ->and($definition->name)->toBe('test_tool')
            ->and($definition->description)->toBe($description);

        $parameters = $definition->parameters;
        expect($parameters)->toBeInstanceOf(ToolParameters::class);

        // Verify the toArray structure of the ToolParameters object
        $paramsArray = $parameters->toArray();
        expect($paramsArray['type'])->toBe('object')
            ->and($paramsArray['required'])->toBe(['param1'])
            ->and($paramsArray['properties'])->toBeObject(); // It should be an object

        // Access properties from the object returned by toArray()
        $propsObject = $paramsArray['properties'];
        expect($propsObject)->toHaveProperty('param1'); // Verify property exists
        $param1Data = $propsObject->param1; // Access as object property
        expect($param1Data)->toBeArray() // The value should be the array from ToolParameter::toArray()
            ->and($param1Data['type'])->toBe('string')
            ->and($param1Data['description'])->toBe('Param 1 desc');
    });

    test('registerTool handles missing optional description and empty params', function (): void {
        $callback = fn () => 'simple';
        // Pass expected empty schema array to registerTool
        $parameterSchema = ['type' => 'object', 'properties' => [], 'required' => []];

        $this->registry->registerTool('simple_tool', $callback, $parameterSchema); // No description

        expect($this->registry->hasTool('simple_tool'))->toBeTrue();
        $tools = $this->registry->getTools();
        expect($tools)->toHaveCount(1)
            ->and($tools[0]->definition->description)->toBe(''); // Default description

        // Verify the toArray structure of the ToolParameters object
        $paramsArray = $tools[0]->definition->parameters->toArray();
        expect($paramsArray['properties'])->toBeObject() // Should be an empty object
            ->and((array) $paramsArray['properties'])->toBe([]); // Casting empty object to array yields empty array
        expect($paramsArray['required'])->toBe([]);
    });

    test('hasTool returns correct boolean', function (): void {
        expect($this->registry->hasTool('non_existent_tool'))->toBeFalse();
        $this->registry->registerTool('exists', fn () => null, []);
        expect($this->registry->hasTool('exists'))->toBeTrue();
    });

    test('getCallback returns correct callback or null', function (): void {
        expect($this->registry->getCallback('non_existent_tool'))->toBeNull();
        $callback = fn () => 'hello';
        $this->registry->registerTool('exists', $callback, []);
        expect($this->registry->getCallback('exists'))->toBe($callback);
    });

    test('executeTool calls callback and returns result', function (): void {
        $callback = function (array $args) {
            return 'Executed with '.$args['message'];
        };
        $parameters = [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'description' => 'The message'],
            ],
            'required' => ['message'],
        ];
        $this->registry->registerTool('executor_test', $callback, $parameters);

        $result = $this->registry->executeTool('executor_test', ['message' => 'success']);
        expect($result)->toBe('Executed with success');
    });

    test('executeTool throws exception for non-existent tool', function (): void {
        $this->registry->executeTool('non_existent', []);
    })->throws(RuntimeException::class, "Tool 'non_existent' not found");

    test('hasTools returns correct boolean', function (): void {
        expect($this->registry->hasTools())->toBeFalse();
        $this->registry->registerTool('a_tool', fn () => null, []);
        expect($this->registry->hasTools())->toBeTrue();
    });

    test('getTools returns array of Tool objects', function (): void {
        expect($this->registry->getTools())->toBe([]);
        $this->registry->registerTool('tool1', fn () => null, []);
        $this->registry->registerTool('tool2', fn () => null, []);
        $tools = $this->registry->getTools();
        expect($tools)->toBeArray()->toHaveCount(2)
            ->and($tools[0])->toBeInstanceOf(Tool::class)
            ->and($tools[1])->toBeInstanceOf(Tool::class);
    });

    test('getTools returns Tool objects whose toArray produces correct API structure', function (): void {
        expect($this->registry->getTools())->toBe([]); // Start empty

        $callback = fn (array $args) => 'result';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'param1' => ['type' => 'string', 'description' => 'Param 1 desc'],
            ],
            'required' => ['param1'],
        ];
        $description = 'Test tool description';
        $this->registry->registerTool('test_tool', $callback, $parameterSchema, $description);

        // Expected array structure AFTER Tool->toArray() is called
        $expectedStructure = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'test_tool',
                    'description' => $description,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [ // Expect properties to be an object here
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

        // Get the list of Tool objects
        $tools = $this->registry->getTools();
        expect($tools)->toHaveCount(1);

        // Call toArray() on the Tool object and compare
        $actualArray = $tools[0]->toArray();

        // Compare structure - use Pest's `toEqual` for deep comparison
        expect($actualArray)->toEqual($expectedStructure[0]);
    });
});
