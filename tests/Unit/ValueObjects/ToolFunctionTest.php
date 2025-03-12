<?php

declare(strict_types=1);

use Shelfwood\LMStudio\ValueObject\ToolFunction;

describe('ToolFunction', function (): void {
    it('can be instantiated with name and description', function (): void {
        $function = new ToolFunction(
            name: 'get_weather',
            description: 'Get the current weather in a given location'
        );

        expect($function->name)->toBe('get_weather');
        expect($function->description)->toBe('Get the current weather in a given location');
        expect($function->parameters)->toBeArray();
        expect($function->parameters)->toBeEmpty();
    });

    it('can be instantiated with parameters', function (): void {
        $parameters = [
            'location' => [
                'type' => 'string',
                'description' => 'The city and state, e.g. San Francisco, CA',
                'required' => true,
            ],
            'unit' => [
                'type' => 'string',
                'enum' => ['celsius', 'fahrenheit'],
                'description' => 'The unit of temperature',
                'required' => false,
            ],
        ];

        $function = new ToolFunction(
            name: 'get_weather',
            description: 'Get the current weather in a given location',
            parameters: $parameters
        );

        expect($function->name)->toBe('get_weather');
        expect($function->description)->toBe('Get the current weather in a given location');
        expect($function->parameters)->toBe($parameters);
    });

    it('serializes to JSON with no parameters', function (): void {
        $function = new ToolFunction(
            name: 'get_time',
            description: 'Get the current time'
        );

        $json = $function->jsonSerialize();

        expect($json)->toBeArray();
        expect($json['name'])->toBe('get_time');
        expect($json['description'])->toBe('Get the current time');
        expect($json['parameters'])->toBeArray();
        expect($json['parameters']['type'])->toBe('object');
        expect($json['parameters']['properties'])->toBeArray();
        expect($json['parameters']['properties'])->toBeEmpty();
    });

    it('serializes to JSON with parameters', function (): void {
        $parameters = [
            'location' => [
                'type' => 'string',
                'description' => 'The city and state, e.g. San Francisco, CA',
                'required' => true,
            ],
            'unit' => [
                'type' => 'string',
                'enum' => ['celsius', 'fahrenheit'],
                'description' => 'The unit of temperature',
                'required' => false,
            ],
        ];

        $function = new ToolFunction(
            name: 'get_weather',
            description: 'Get the current weather in a given location',
            parameters: $parameters
        );

        $json = $function->jsonSerialize();

        expect($json)->toBeArray();
        expect($json['name'])->toBe('get_weather');
        expect($json['description'])->toBe('Get the current weather in a given location');
        expect($json['parameters'])->toBeArray();
        expect($json['parameters']['type'])->toBe('object');
        expect($json['parameters']['properties'])->toBe($parameters);
        expect($json['parameters']['required'])->toBeArray();
        expect($json['parameters']['required'])->toContain('location');
        expect($json['parameters']['required'])->not->toContain('unit');
    });
});
