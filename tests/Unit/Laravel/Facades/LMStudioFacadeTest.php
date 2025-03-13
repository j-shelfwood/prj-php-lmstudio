<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use Shelfwood\LMStudio\Laravel\Facades\LMStudio;

describe('LMStudioFacade', function (): void {
    test('facade extends Illuminate Facade', function (): void {
        expect(LMStudio::class)->toExtend(Facade::class);
    });

    test('facade accessor is lmstudio', function (): void {
        $reflection = new ReflectionClass(LMStudio::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);

        expect($method->invoke(null))->toBe('lmstudio');
    });
});
