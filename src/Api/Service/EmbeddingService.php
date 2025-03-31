<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Service;

use Shelfwood\LMStudio\Api\Exception\ApiException;
use Shelfwood\LMStudio\Api\Exception\ValidationException;
use Shelfwood\LMStudio\Api\Response\EmbeddingResponse;

class EmbeddingService extends AbstractService
{
    /**
     * Create an embedding.
     *
     * @param  string  $model  The model to use
     * @param  string|array  $input  The input to embed
     * @param  array  $options  Additional options
     *
     * @throws ApiException If the request fails
     * @throws ValidationException If the request is invalid
     */
    public function createEmbedding(string $model, $input, array $options = []): EmbeddingResponse
    {
        if (empty($model)) {
            throw new ValidationException('Model is required');
        }

        if (empty($input)) {
            throw new ValidationException('Input is required');
        }

        $data = array_merge([
            'model' => $model,
            'input' => $input,
        ], $options);

        $response = $this->apiClient->post('/api/v0/embeddings', $data);

        return EmbeddingResponse::fromArray($response);
    }
}
