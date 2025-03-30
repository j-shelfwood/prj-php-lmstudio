<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;

describe('ToolDefinition', function (): void {
    test('can create tool definition', function (): void {
        $parameters = new ToolParameters;
        $parameters->addProperty('test', new ToolParameter('string', 'A test parameter'));
        $parameters->addRequired('test');

        $definition = new ToolDefinition('test_function', 'A test function', $parameters);

        expect($definition->name)->toBe('test_function')
            ->and($definition->description)->toBe('A test function')
            ->and($definition->parameters)->toBe($parameters);
    });

    test('can convert to array', function (): void {
        $parameters = new ToolParameters;
        $parameters->addProperty('test', new ToolParameter('string', 'A test parameter'));
        $parameters->addRequired('test');

        $definition = new ToolDefinition('test_function', 'A test function', $parameters);

        $expected = [
            'name' => 'test_function',
            'description' => 'A test function',
            'parameters' => [
                'type' => 'object',
                'properties' => (object) [
                    'test' => [
                        'type' => 'string',
                        'description' => 'A test parameter',
                    ],
                ],
                'required' => ['test'],
            ],
        ];

        $actualArray = $definition->toArray();

        // Check parts separately and cast properties
        expect($actualArray['name'])->toBe('test_function')
            ->and($actualArray['description'])->toBe('A test function')
            ->and($actualArray['parameters']['type'])->toBe('object')
            ->and($actualArray['parameters']['required'])->toBe(['test'])
            ->and((array) $actualArray['parameters']['properties'])->toBe([
                'test' => [
                    'type' => 'string',
                    'description' => 'A test parameter',
                ],
            ]);
    });
});
