<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Enum\ModelState;
use Shelfwood\LMStudio\Enum\ModelType;
use Shelfwood\LMStudio\Model\ModelInfo;

test('model info can be created from array', function (): void {
    $data = [
        'id' => 'model1',
        'object' => 'model',
        'type' => 'llm',
        'publisher' => 'publisher1',
        'arch' => 'arch1',
        'compatibility_type' => 'compat1',
        'quantization' => 'quant1',
        'state' => 'loaded',
        'max_context_length' => 4096,
    ];

    $model = ModelInfo::fromArray($data);

    expect($model)->toBeInstanceOf(ModelInfo::class);
    expect($model->getId())->toBe('model1');
    expect($model->getObject())->toBe('model');
    expect($model->getType())->toBe(ModelType::LLM);
    expect($model->getPublisher())->toBe('publisher1');
    expect($model->getArch())->toBe('arch1');
    expect($model->getCompatibilityType())->toBe('compat1');
    expect($model->getQuantization())->toBe('quant1');
    expect($model->getState())->toBe(ModelState::LOADED);
    expect($model->getMaxContextLength())->toBe(4096);
});

test('model info can check if model is loaded', function (): void {
    $loadedModel = ModelInfo::fromArray([
        'id' => 'model1',
        'object' => 'model',
        'type' => 'llm',
        'publisher' => 'publisher1',
        'arch' => 'arch1',
        'compatibility_type' => 'compat1',
        'quantization' => 'quant1',
        'state' => 'loaded',
        'max_context_length' => 4096,
    ]);

    $notLoadedModel = ModelInfo::fromArray([
        'id' => 'model2',
        'object' => 'model',
        'type' => 'llm',
        'publisher' => 'publisher2',
        'arch' => 'arch2',
        'compatibility_type' => 'compat2',
        'quantization' => 'quant2',
        'state' => 'not-loaded',
        'max_context_length' => 8192,
    ]);

    expect($loadedModel->isLoaded())->toBeTrue();
    expect($notLoadedModel->isLoaded())->toBeFalse();
});

test('model info can get properties', function (): void {
    $model = new ModelInfo(
        'model1',
        'model',
        ModelType::LLM,
        'publisher1',
        'arch1',
        'compat1',
        'quant1',
        ModelState::LOADED,
        4096
    );

    expect($model->getId())->toBe('model1');
    expect($model->getObject())->toBe('model');
    expect($model->getType())->toBe(ModelType::LLM);
    expect($model->getPublisher())->toBe('publisher1');
    expect($model->getArch())->toBe('arch1');
    expect($model->getCompatibilityType())->toBe('compat1');
    expect($model->getQuantization())->toBe('quant1');
    expect($model->getState())->toBe(ModelState::LOADED);
    expect($model->getMaxContextLength())->toBe(4096);
});
