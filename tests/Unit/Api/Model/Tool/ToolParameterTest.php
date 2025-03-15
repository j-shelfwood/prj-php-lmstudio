<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;

test('can create tool parameter', function (): void {
    $parameter = new ToolParameter('string', 'A test parameter');

    expect($parameter->getType())->toBe('string')
        ->and($parameter->getDescription())->toBe('A test parameter');
});

test('can convert to array', function (): void {
    $parameter = new ToolParameter('string', 'A test parameter');

    expect($parameter->toArray())->toBe([
        'type' => 'string',
        'description' => 'A test parameter',
    ]);
});
