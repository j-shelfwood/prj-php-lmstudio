<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;

describe('ToolParameter', function (): void {
    test('can create tool parameter', function (): void {
        $parameter = new ToolParameter('string', 'A test parameter');

        expect($parameter->type)->toBe('string')
            ->and($parameter->description)->toBe('A test parameter');
    });

    test('can convert to array', function (): void {
        $parameter = new ToolParameter('string', 'A test parameter');

        $expected = [
            'type' => 'string',
            'description' => 'A test parameter',
        ];

        expect($parameter->toArray())->toBe($expected);
    });
});
