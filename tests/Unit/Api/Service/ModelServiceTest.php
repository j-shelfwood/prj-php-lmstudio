<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Api\Enum\ModelState;
use Shelfwood\LMStudio\Api\Enum\ModelType;
use Shelfwood\LMStudio\Api\Model\ModelInfo;
use Shelfwood\LMStudio\Api\Response\ModelResponse;
use Shelfwood\LMStudio\Api\Service\ModelService;

describe('ModelService', function (): void {
    beforeEach(function (): void {
        $this->apiClient = Mockery::mock(ApiClientInterface::class);
        $this->modelService = new ModelService($this->apiClient);
    });

    test('list models returns expected models', function (): void {
        // Load the mock response
        $mockResponse = load_mock('models/list.json');

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
        expect($models[0]->getId())->toBe('qwen2.5-7b-instruct');
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
        $mockListResponse = load_mock('models/list.json');
        $mockModelResponse = $mockListResponse['data'][0];

        // Set up the mock to return the mock response
        $this->apiClient->shouldReceive('get')
            ->once()
            ->with('/api/v0/models/qwen2.5-7b-instruct')
            ->andReturn($mockModelResponse);

        // Call the getModel method
        $model = $this->modelService->getModel('qwen2.5-7b-instruct');

        // Assert the model is a ModelInfo
        expect($model)->toBeInstanceOf(ModelInfo::class);

        // Assert the model contains the correct data
        expect($model->getId())->toBe('qwen2.5-7b-instruct');
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
});
