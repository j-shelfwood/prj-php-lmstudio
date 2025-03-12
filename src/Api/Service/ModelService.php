<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Service;

use Shelfwood\LMStudio\Api\Exception\ApiException;
use Shelfwood\LMStudio\Api\Model\ModelInfo;
use Shelfwood\LMStudio\Api\Response\ModelResponse;

class ModelService extends AbstractService
{
    /**
     * List all models.
     *
     * @return ModelResponse
     * @throws ApiException If the request fails
     */
    public function listModels(): ModelResponse
    {
        $response = $this->apiClient->get('/api/v0/models');
        return ModelResponse::fromArray($response);
    }

    /**
     * Get a specific model.
     *
     * @param string $modelId The model ID
     * @return ModelInfo
     * @throws ApiException If the request fails
     */
    public function getModel(string $modelId): ModelInfo
    {
        $response = $this->apiClient->get("/api/v0/models/{$modelId}");
        return ModelInfo::fromArray($response);
    }
}