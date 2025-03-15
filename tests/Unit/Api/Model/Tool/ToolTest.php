<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;

test('can create tool with type and function', function (): void {
    $definition = new ToolDefinition(
        'test_function',
        'A test function',
        new ToolParameters([
            'string' => new ToolParameter('string', 'A test parameter'),
        ])
    );

    $tool = new Tool(ToolType::FUNCTION, $definition);

    expect($tool->getType())->toBe(ToolType::FUNCTION)
        ->and($tool->getDefinition())->toBe($definition);
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

    expect($tool->toArray())->toBe([
        'type' => 'function',
        'function' => [
            'name' => 'test_function',
            'description' => 'A test function',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'string' => [
                        'type' => 'string',
                        'description' => 'A test parameter',
                    ],
                ],
                'required' => [],
            ],
        ],
    ]);
});

test('can create tool from array', function (): void {
    $array = [
        'type' => 'function',
        'function' => [
            'name' => 'test_function',
            'description' => 'A test function',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'string' => [
                        'type' => 'string',
                        'description' => 'A test parameter',
                    ],
                ],
                'required' => [],
            ],
        ],
    ];

    $tool = Tool::fromArray($array);

    expect($tool)->toBeInstanceOf(Tool::class)
        ->and($tool->getType())->toBe(ToolType::FUNCTION)
        ->and($tool->getDefinition())->toBeInstanceOf(ToolDefinition::class)
        ->and($tool->getDefinition()->getName())->toBe('test_function')
        ->and($tool->getDefinition()->getDescription())->toBe('A test function')
        ->and($tool->getDefinition()->getParameters())->toBeInstanceOf(ToolParameters::class)
        ->and($tool->getDefinition()->getParameters()->toArray())->toBe([
            'type' => 'object',
            'properties' => [
                'string' => [
                    'type' => 'string',
                    'description' => 'A test parameter',
                ],
            ],
            'required' => [],
        ]);
});
