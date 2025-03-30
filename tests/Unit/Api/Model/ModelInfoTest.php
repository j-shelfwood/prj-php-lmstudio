<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Enum\ModelState;
use Shelfwood\LMStudio\Api\Enum\ModelType;
use Shelfwood\LMStudio\Api\Model\ModelInfo;

describe('ToolExecutionHandler', function (): void {
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
        expect($model->id)->toBe('model1');
        expect($model->object)->toBe('model');
        expect($model->type)->toBe(ModelType::LLM);
        expect($model->publisher)->toBe('publisher1');
        expect($model->arch)->toBe('arch1');
        expect($model->compatibilityType)->toBe('compat1');
        expect($model->quantization)->toBe('quant1');
        expect($model->state)->toBe(ModelState::LOADED);
        expect($model->maxContextLength)->toBe(4096);
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

        expect($model->id)->toBe('model1');
        expect($model->object)->toBe('model');
        expect($model->type)->toBe(ModelType::LLM);
        expect($model->publisher)->toBe('publisher1');
        expect($model->arch)->toBe('arch1');
        expect($model->compatibilityType)->toBe('compat1');
        expect($model->quantization)->toBe('quant1');
        expect($model->state)->toBe(ModelState::LOADED);
        expect($model->maxContextLength)->toBe(4096);
    });
});
