<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;

test('can create tool definition', function (): void {
    $parameters = new ToolParameters([
        'string' => new ToolParameter('string', 'A test parameter'),
    ]);

    $definition = new ToolDefinition('test_function', 'A test function', $parameters);

    expect($definition->getName())->toBe('test_function')
        ->and($definition->getDescription())->toBe('A test function')
        ->and($definition->getParameters())->toBe($parameters);
});

test('can convert to array', function (): void {
    $parameters = new ToolParameters([
        'string' => new ToolParameter('string', 'A test parameter'),
    ]);

    $definition = new ToolDefinition('test_function', 'A test function', $parameters);

    expect($definition->toArray())->toBe([
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
    ]);
});
