<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Api\Response\TextCompletionResponse;
use Shelfwood\LMStudio\Api\Service\CompletionService;

describe('CompletionService', function (): void {
    beforeEach(function (): void {
        $this->apiClient = Mockery::mock(ApiClientInterface::class);
        $this->completionService = new CompletionService($this->apiClient);
    });

    test('create completion returns expected response', function (): void {
        // Load the mock response
        $mockResponse = load_mock('completions/standard-response.json');

        // Set up the mock to return the mock response
        $this->apiClient->shouldReceive('post')
            ->once()
            ->with('/api/v0/completions', [
                'model' => 'qwen2.5-7b-instruct',
                'prompt' => 'What is',
            ])
            ->andReturn($mockResponse);

        // Call the createCompletion method
        $response = $this->completionService->createCompletion('qwen2.5-7b-instruct', 'What is');

        // Assert the response is a TextCompletionResponse
        expect($response)->toBeInstanceOf(TextCompletionResponse::class);

        // Assert the response contains the correct data
        expect($response->id)->toBe('cmpl-4gtjyxdfethjgyakm6xuq');
        expect($response->object)->toBe('text_completion');
        expect($response->model)->toBe('qwen2.5-7b-instruct');
        expect($response->getChoices())->toHaveCount(1);

        // Assert the content is correct
        expect($response->getChoices()[0]['text'])->toBe(' a profound and complex question that has puzzled philosophers');
    });

    test('create completion with options returns expected response', function (): void {
        // Load the mock response
        $mockResponse = load_mock('completions/standard-response.json');

        // Set up the mock to return the mock response
        $this->apiClient->shouldReceive('post')
            ->once()
            ->with('/api/v0/completions', [
                'model' => 'qwen2.5-7b-instruct',
                'prompt' => 'What is',
                'temperature' => 0.7,
                'max_tokens' => 100,
            ])
            ->andReturn($mockResponse);

        // Call the createCompletion method with options
        $response = $this->completionService->createCompletion('qwen2.5-7b-instruct', 'What is', [
            'temperature' => 0.7,
            'max_tokens' => 100,
        ]);

        // Assert the response is a TextCompletionResponse
        expect($response)->toBeInstanceOf(TextCompletionResponse::class);

        // Assert the response contains the correct data
        expect($response->id)->toBe('cmpl-4gtjyxdfethjgyakm6xuq');
        expect($response->object)->toBe('text_completion');
        expect($response->model)->toBe('qwen2.5-7b-instruct');

        // Assert the usage data is correct
        expect($response->usage)->toBe([
            'prompt_tokens' => 5,
            'completion_tokens' => 9,
            'total_tokens' => 14,
        ]);
    });
});
