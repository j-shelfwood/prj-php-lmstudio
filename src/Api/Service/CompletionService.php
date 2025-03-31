<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Service;

use Shelfwood\LMStudio\Api\Exception\ApiException;
use Shelfwood\LMStudio\Api\Exception\ValidationException;
use Shelfwood\LMStudio\Api\Response\TextCompletionResponse;

class CompletionService extends AbstractService
{
    /**
     * Create a text completion.
     *
     * @param  string  $model  The model to use
     * @param  string  $prompt  The prompt to complete
     * @param  array  $options  Additional options
     *
     * @throws ApiException If the request fails
     * @throws ValidationException If the request is invalid
     */
    public function createCompletion(string $model, string $prompt, array $options = []): TextCompletionResponse
    {
        if (empty($model)) {
            throw new ValidationException('Model is required');
        }

        if (empty($prompt)) {
            throw new ValidationException('Prompt is required');
        }

        $data = array_merge([
            'model' => $model,
            'prompt' => $prompt,
        ], $options);

        $response = $this->apiClient->post('/api/v0/completions', $data);

        return TextCompletionResponse::fromArray($response);
    }
}
