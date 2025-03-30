<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;

test('can create tool with type and function', function (): void {
    $parameters = new ToolParameters;
    $parameters->addProperty('string', new ToolParameter('string', 'A test parameter'));
    $definition = new ToolDefinition(
        'test_function',
        'A test function',
        $parameters
    );

    $tool = new Tool(ToolType::FUNCTION, $definition);

    expect($tool->type)->toBe(ToolType::FUNCTION)
        ->and($tool->definition)->toBe($definition);
});

test('can convert tool to array', function (): void {
    $definition = new ToolDefinition(
        'test_function',
        'A test function',
        new ToolParameters([
            'string' => new ToolParameter('string', 'A test parameter'),
        ])
    );

    $tool = new Tool(ToolType::FUNCTION, $definition);

    $actualArray = $tool->toArray();

    // Check parts separately and cast properties
    expect($actualArray['type'])->toBe(ToolType::FUNCTION->value)
        ->and($actualArray['function']['name'])->toBe('test_function')
        ->and($actualArray['function']['description'])->toBe('A test function')
        ->and($actualArray['function']['parameters']['type'])->toBe('object')
        ->and($actualArray['function']['parameters']['required'])->toBe([])
        ->and((array) $actualArray['function']['parameters']['properties'])->toBe([
            'string' => [
                'type' => 'string',
                'description' => 'A test parameter',
            ],
        ]);
});

test('can create tool from array', function (): void {
    $parametersArray = [
        'type' => 'object',
        'properties' => [ // Keep expected as array
            'string' => [
                'type' => 'string',
                'description' => 'A test parameter',
            ],
        ],
        'required' => [],
    ];
    $array = [
        'type' => 'function',
        'function' => [
            'name' => 'test_function',
            'description' => 'A test function',
            'parameters' => $parametersArray,
        ],
    ];

    $tool = Tool::fromArray($array);

    $expectedParameter = new ToolParameter('string', 'A test parameter');
    $expectedParameters = new ToolParameters;
    $expectedParameters->addProperty('string', $expectedParameter);

    $expectedDefinition = new ToolDefinition('test_function', 'A test function', $expectedParameters);

    expect($tool)->toBeInstanceOf(Tool::class)
        ->and($tool->type)->toBe(ToolType::FUNCTION)
        ->and($tool->definition)->toBeInstanceOf(ToolDefinition::class)
        ->and($tool->definition->name)->toBe('test_function')
        ->and($tool->definition->description)->toBe('A test function')
        ->and($tool->definition->parameters)->toBeInstanceOf(ToolParameters::class);

    // Compare the array output, casting the properties part
    $actualParamsArray = $tool->definition->parameters->toArray();
    expect($actualParamsArray['type'])->toBe('object')
        ->and((array) $actualParamsArray['properties'])->toBe($parametersArray['properties']) // Cast and compare props
        ->and($actualParamsArray['required'])->toBe($parametersArray['required']); // Compare required
});
