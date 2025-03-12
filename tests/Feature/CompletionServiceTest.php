<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Api\Response\TextCompletionResponse;
use Shelfwood\LMStudio\Api\Service\CompletionService;

beforeEach(function (): void {
    $this->apiClient = Mockery::mock(ApiClientInterface::class);
    $this->completionService = new CompletionService($this->apiClient);
});

test('create completion returns expected response', function (): void {
    // Load the mock response
    $mockResponse = json_decode(file_get_contents(__DIR__.'/../mocks/completions/standard-response.json'), true);

    // Set up the mock to return the mock response
    $this->apiClient->shouldReceive('post')
        ->once()
        ->with('/api/v0/completions', [
            'model' => 'qwen2.5-7b-instruct-1m',
            'prompt' => 'Once upon a time',
            'max_tokens' => 50,
        ])
        ->andReturn($mockResponse);

    // Call the createCompletion method
    $response = $this->completionService->createCompletion('qwen2.5-7b-instruct-1m', 'Once upon a time', [
        'max_tokens' => 50,
    ]);

    // Assert the response is a TextCompletionResponse
    expect($response)->toBeInstanceOf(TextCompletionResponse::class);

    // Assert the response contains the correct data
    expect($response->id)->toBe('cmpl-l7o25hmi2eogutjith3ljs');
    expect($response->object)->toBe('text_completion');
    expect($response->model)->toBe('qwen2.5-7b-instruct-1m');
    expect($response->getChoices())->toHaveCount(1);

    // Assert the content is correct
    $expectedText = ', there was a young girl named Lily who lived in a small village nestled between the mountains and the sea. She had always been fascinated by the stories her grandmother told her about the magical creatures';
    expect($response->getChoices()[0]['text'])->toBe($expectedText);
    expect($response->getText())->toBe($expectedText);
});

test('create completion with options returns expected response', function (): void {
    // Load the mock response
    $mockResponse = json_decode(file_get_contents(__DIR__.'/../mocks/completions/standard-response.json'), true);

    // Set up the mock to return the mock response
    $this->apiClient->shouldReceive('post')
        ->once()
        ->with('/api/v0/completions', [
            'model' => 'qwen2.5-7b-instruct-1m',
            'prompt' => 'Once upon a time',
            'max_tokens' => 50,
            'temperature' => 0.7,
            'top_p' => 0.9,
        ])
        ->andReturn($mockResponse);

    // Call the createCompletion method with additional options
    $response = $this->completionService->createCompletion('qwen2.5-7b-instruct-1m', 'Once upon a time', [
        'max_tokens' => 50,
        'temperature' => 0.7,
        'top_p' => 0.9,
    ]);

    // Assert the response is a TextCompletionResponse
    expect($response)->toBeInstanceOf(TextCompletionResponse::class);

    // Assert the response contains the correct data
    expect($response->id)->toBe('cmpl-l7o25hmi2eogutjith3ljs');
    expect($response->object)->toBe('text_completion');
    expect($response->model)->toBe('qwen2.5-7b-instruct-1m');

    // Assert the usage data is correct
    expect($response->usage['prompt_tokens'])->toBe(4);
    expect($response->usage['completion_tokens'])->toBe(49);
    expect($response->usage['total_tokens'])->toBe(53);
});
