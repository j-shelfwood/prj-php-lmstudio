<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Model\Tool;

describe('StreamingHandler', function (): void {
    test('tool can be created with type and function', function (): void {
        $function = [
            'name' => 'get_weather',
            'description' => 'Get the weather for a location',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The location to get weather for',
                    ],
                ],
                'required' => ['location'],
            ],
        ];

        $tool = new Tool(ToolType::FUNCTION, $function);

        expect($tool)->toBeInstanceOf(Tool::class);
        expect($tool->getType())->toBe(ToolType::FUNCTION);
        expect($tool->getFunction())->toBe($function);
    });

    test('tool can be converted to array', function (): void {
        $function = [
            'name' => 'get_weather',
            'description' => 'Get the weather for a location',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The location to get weather for',
                    ],
                ],
                'required' => ['location'],
            ],
        ];

        $tool = new Tool(ToolType::FUNCTION, $function);
        $array = $tool->toArray();

        expect($array)->toBe([
            'type' => 'function',
            'function' => $function,
        ]);
    });

    test('tool can be created from array', function (): void {
        $function = [
            'name' => 'get_weather',
            'description' => 'Get the weather for a location',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The location to get weather for',
                    ],
                ],
                'required' => ['location'],
            ],
        ];

        $array = [
            'type' => 'function',
            'function' => $function,
        ];

        $tool = Tool::fromArray($array);

        expect($tool)->toBeInstanceOf(Tool::class);
        expect($tool->getType())->toBe(ToolType::FUNCTION);
        expect($tool->getFunction())->toBe($function);
    });
});
