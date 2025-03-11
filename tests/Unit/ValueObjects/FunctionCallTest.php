<?php

declare(strict_types=1);

use Shelfwood\LMStudio\ValueObjects\FunctionCall;

describe('FunctionCall', function (): void {
    it('can be instantiated with name and arguments', function (): void {
        $functionCall = new FunctionCall(
            name: 'get_weather',
            arguments: '{"location":"San Francisco"}'
        );

        expect($functionCall->name)->toBe('get_weather');
        expect($functionCall->arguments)->toBe('{"location":"San Francisco"}');
    });

    it('serializes to JSON correctly', function (): void {
        $functionCall = new FunctionCall(
            name: 'get_weather',
            arguments: '{"location":"San Francisco"}'
        );

        $json = $functionCall->jsonSerialize();

        expect($json)->toBeArray();
        expect($json['name'])->toBe('get_weather');
        expect($json['arguments'])->toBe('{"location":"San Francisco"}');
    });

    it('can get arguments as array', function (): void {
        $functionCall = new FunctionCall(
            name: 'get_weather',
            arguments: '{"location":"San Francisco","unit":"celsius"}'
        );

        $args = $functionCall->getArgumentsAsArray();

        expect($args)->toBeArray();
        expect($args['location'])->toBe('San Francisco');
        expect($args['unit'])->toBe('celsius');
    });

    it('returns empty array for invalid JSON arguments', function (): void {
        $functionCall = new FunctionCall(
            name: 'get_weather',
            arguments: 'invalid json',
            skipValidation: true
        );

        $args = $functionCall->getArgumentsAsArray();

        expect($args)->toBeArray();
        expect($args)->toBeEmpty();
    });
});
