<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Responses\Common\Function_;

describe('Function_', function (): void {
    it('can be instantiated with name and arguments', function (): void {
        $function = new Function_(
            name: 'get_weather',
            arguments: '{"location":"San Francisco"}'
        );

        expect($function->name)->toBe('get_weather');
        expect($function->arguments)->toBe('{"location":"San Francisco"}');
    });

    it('can be created from an array', function (): void {
        $data = [
            'name' => 'get_weather',
            'arguments' => '{"location":"San Francisco","unit":"celsius"}',
        ];

        $function = Function_::fromArray($data);

        expect($function->name)->toBe('get_weather');
        expect($function->arguments)->toBe('{"location":"San Francisco","unit":"celsius"}');
    });

    it('handles missing data when created from array', function (): void {
        $function = Function_::fromArray([]);

        expect($function->name)->toBe('');
        expect($function->arguments)->toBe('{}');
    });

    it('can get arguments as array', function (): void {
        $function = new Function_(
            name: 'get_weather',
            arguments: '{"location":"San Francisco","unit":"celsius"}'
        );

        $args = $function->getArgumentsAsArray();

        expect($args)->toBeArray();
        expect($args['location'])->toBe('San Francisco');
        expect($args['unit'])->toBe('celsius');
    });

    it('returns empty array for invalid JSON arguments', function (): void {
        $function = new Function_(
            name: 'get_weather',
            arguments: 'invalid json'
        );

        $args = $function->getArgumentsAsArray();

        expect($args)->toBeArray();
        expect($args)->toBeEmpty();
    });
});
