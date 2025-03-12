<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Enum\ModelState;
use Shelfwood\LMStudio\Enum\ModelType;
use Shelfwood\LMStudio\Model\ModelInfo;
use Shelfwood\LMStudio\Response\ModelResponse;
use Shelfwood\LMStudio\Service\ModelService;

beforeEach(function (): void {
    $this->apiClient = Mockery::mock(ApiClientInterface::class);
    $this->modelService = new ModelService($this->apiClient);
});

test('list models returns expected models', function (): void {
    // Load the mock response
    $mockResponse = json_decode(file_get_contents(__DIR__.'/../mocks/models/list.json'), true);

    // Set up the mock to return the mock response
    $this->apiClient->shouldReceive('get')
        ->once()
        ->with('/api/v0/models')
        ->andReturn($mockResponse);

    // Call the listModels method
    $response = $this->modelService->listModels();

    // Assert the response is a ModelResponse
    expect($response)->toBeInstanceOf(ModelResponse::class);

    // Assert the response contains the correct data
    expect($response->getObject())->toBe('list');
    expect($response->getData())->toBe($mockResponse['data']);

    // Assert the models are correctly parsed
    $models = $response->getModels();
    expect($models)->toHaveCount(7);

    // Check the first model
    expect($models[0])->toBeInstanceOf(ModelInfo::class);
    expect($models[0]->getId())->toBe('qwen2.5-7b-instruct-1m');
    expect($models[0]->getType())->toBe(ModelType::LLM);
    expect($models[0]->getState())->toBe(ModelState::LOADED);
    expect($models[0]->isLoaded())->toBeTrue();

    // Check an embedding model
    $embeddingModel = null;

    foreach ($models as $model) {
        if ($model->getType() === ModelType::EMBEDDINGS) {
            $embeddingModel = $model;

            break;
        }
    }

    expect($embeddingModel)->not->toBeNull();
    expect($embeddingModel->getType())->toBe(ModelType::EMBEDDINGS);
});

test('get model returns expected model', function (): void {
    // Use the first model from the list as our mock response
    $mockListResponse = json_decode(file_get_contents(__DIR__.'/../mocks/models/list.json'), true);
    $mockModelResponse = $mockListResponse['data'][0];

    // Set up the mock to return the mock response
    $this->apiClient->shouldReceive('get')
        ->once()
        ->with('/api/v0/models/qwen2.5-7b-instruct-1m')
        ->andReturn($mockModelResponse);

    // Call the getModel method
    $model = $this->modelService->getModel('qwen2.5-7b-instruct-1m');

    // Assert the model is a ModelInfo
    expect($model)->toBeInstanceOf(ModelInfo::class);

    // Assert the model contains the correct data
    expect($model->getId())->toBe('qwen2.5-7b-instruct-1m');
    expect($model->getObject())->toBe('model');
    expect($model->getType())->toBe(ModelType::LLM);
    expect($model->getPublisher())->toBe('lmstudio-community');
    expect($model->getArch())->toBe('granite');
    expect($model->getCompatibilityType())->toBe('gguf');
    expect($model->getQuantization())->toBe('Q8_0');
    expect($model->getState())->toBe(ModelState::LOADED);
    expect($model->getMaxContextLength())->toBe(131072);
    expect($model->isLoaded())->toBeTrue();
});
