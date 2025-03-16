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

        // Assert the response data matches the mock
        expect($response->getObject())->toBe('list');
        expect($response->getData())->toBe($mockResponse['data']);

        // Assert the models are correctly parsed
        $models = $response->getModels();
        expect($models)->toHaveCount(8);

        // Check the first model
        expect($models[0])->toBeInstanceOf(ModelInfo::class);
        expect($models[0]->getId())->toBe('qwen2.5-7b-instruct');
        expect($models[0]->getObject())->toBe('model');
        expect($models[0]->getType())->toBe(ModelType::LLM);
        expect($models[0]->getPublisher())->toBe('lmstudio-community');
        expect($models[0]->getArch())->toBe('qwen2');
        expect($models[0]->getCompatibilityType())->toBe('gguf');
        expect($models[0]->getQuantization())->toBe('Q8_0');
        expect($models[0]->getState())->toBe(ModelState::LOADED);
        expect($models[0]->getMaxContextLength())->toBe(32768);
    });

    test('get model returns expected model', function (): void {
        // Load the mock response
        $mockResponse = $mockResponse = [
            'id' => 'qwen2.5-7b-instruct',
            'object' => 'model',
            'type' => 'llm',
            'publisher' => 'lmstudio-community',
            'arch' => 'qwen2',
            'compatibility_type' => 'gguf',
            'quantization' => 'Q8_0',
            'state' => 'loaded',
            'max_context_length' => 32768,
            'loaded_context_length' => 4096,
        ];

        // Set up the mock to return the mock response
        $this->apiClient->shouldReceive('get')
            ->once()
            ->with('/api/v0/models/qwen2.5-7b-instruct')
            ->andReturn($mockResponse);

        // Call the getModel method
        $model = $this->modelService->getModel('qwen2.5-7b-instruct');

        // Assert the model is correctly parsed
        expect($model)->toBeInstanceOf(ModelInfo::class);
        expect($model->getId())->toBe('qwen2.5-7b-instruct');
        expect($model->getObject())->toBe('model');
        expect($model->getType())->toBe(ModelType::LLM);
        expect($model->getPublisher())->toBe('lmstudio-community');
        expect($model->getArch())->toBe('qwen2');
        expect($model->getCompatibilityType())->toBe('gguf');
        expect($model->getQuantization())->toBe('Q8_0');
        expect($model->getState())->toBe(ModelState::LOADED);
        expect($model->getMaxContextLength())->toBe(32768);
    });
});
