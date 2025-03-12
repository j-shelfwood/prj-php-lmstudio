<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Api\Model\ModelInfo;
use Shelfwood\LMStudio\Api\Response\ModelResponse;
use Shelfwood\LMStudio\Api\Service\ModelService;

beforeEach(function (): void {
    $this->apiClient = Mockery::mock(ApiClientInterface::class);
    $this->modelService = new ModelService($this->apiClient);
});

test('list models returns model response', function (): void {
    // Mock the API response
    $apiResponse = [
        'object' => 'list',
        'data' => [
            [
                'id' => 'model1',
                'object' => 'model',
                'type' => 'llm',
                'publisher' => 'publisher1',
                'arch' => 'arch1',
                'compatibility_type' => 'compat1',
                'quantization' => 'quant1',
                'state' => 'loaded',
                'max_context_length' => 4096,
            ],
            [
                'id' => 'model2',
                'object' => 'model',
                'type' => 'embeddings',
                'publisher' => 'publisher2',
                'arch' => 'arch2',
                'compatibility_type' => 'compat2',
                'quantization' => 'quant2',
                'state' => 'not-loaded',
                'max_context_length' => 8192,
            ],
        ],
    ];

    // Set up the mock to return the API response
    $this->apiClient->shouldReceive('get')
        ->with('/api/v0/models')
        ->andReturn($apiResponse);

    // Call the listModels method
    $response = $this->modelService->listModels();

    // Assert the response is a ModelResponse
    expect($response)->toBeInstanceOf(ModelResponse::class);

    // Assert the response contains the correct data
    expect($response->getObject())->toBe('list');
    expect($response->getData())->toBe($apiResponse['data']);

    // Assert the models are correctly parsed
    $models = $response->getModels();
    expect($models)->toHaveCount(2);
    expect($models[0])->toBeInstanceOf(ModelInfo::class);
    expect($models[0]->getId())->toBe('model1');
    expect($models[0]->isLoaded())->toBeTrue();
    expect($models[1])->toBeInstanceOf(ModelInfo::class);
    expect($models[1]->getId())->toBe('model2');
    expect($models[1]->isLoaded())->toBeFalse();
});

test('get model returns model info', function (): void {
    // Mock the API response
    $apiResponse = [
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

    // Set up the mock to return the API response
    $this->apiClient->shouldReceive('get')
        ->with('/api/v0/models/model1')
        ->andReturn($apiResponse);

    // Call the getModel method
    $model = $this->modelService->getModel('model1');

    // Assert the model is a ModelInfo
    expect($model)->toBeInstanceOf(ModelInfo::class);

    // Assert the model contains the correct data
    expect($model->getId())->toBe('model1');
    expect($model->getObject())->toBe('model');
    expect($model->getType()->value)->toBe('llm');
    expect($model->getPublisher())->toBe('publisher1');
    expect($model->getArch())->toBe('arch1');
    expect($model->getCompatibilityType())->toBe('compat1');
    expect($model->getQuantization())->toBe('quant1');
    expect($model->getState()->value)->toBe('loaded');
    expect($model->getMaxContextLength())->toBe(4096);
    expect($model->isLoaded())->toBeTrue();
});
