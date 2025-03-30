<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Model\Tool;

use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;

it('can create empty tool parameters', function (): void {
    $parameters = new ToolParameters;

    $array = $parameters->toArray();

    expect($array['type'])->toBe('object')
        ->and((array) $array['properties'])->toBe([])
        ->and($array['required'])->toBe([]);
});

it('can add properties', function (): void {
    $parameters = new ToolParameters;
    $parameter = new ToolParameter('string', 'A test parameter');

    $parameters->addProperty('test', $parameter);

    $array = $parameters->toArray();

    $expectedPropertiesArray = ['test' => ['type' => 'string', 'description' => 'A test parameter']];

    expect($array['type'])->toBe('object')
        ->and((array) $array['properties'])->toBe($expectedPropertiesArray)
        ->and($array['required'])->toBe([]);
});

it('can add required properties', function (): void {
    $parameters = new ToolParameters;
    $parameter = new ToolParameter('string', 'A test parameter');

    $parameters
        ->addProperty('test', $parameter)
        ->addRequired('test');

    $array = $parameters->toArray();

    $expectedPropertiesArray = ['test' => ['type' => 'string', 'description' => 'A test parameter']];

    expect($array['type'])->toBe('object')
        ->and((array) $array['properties'])->toBe($expectedPropertiesArray)
        ->and($array['required'])->toBe(['test']);
});

it('does not duplicate required properties', function (): void {
    $parameters = new ToolParameters;

    $parameters
        ->addRequired('test')
        ->addRequired('test');

    $array = $parameters->toArray();

    expect($array['type'])->toBe('object')
        ->and((array) $array['properties'])->toBe([])
        ->and($array['required'])->toBe(['test']);
});
