<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Service;

use Shelfwood\LMStudio\Exception\ApiException;
use Shelfwood\LMStudio\Model\ModelInfo;
use Shelfwood\LMStudio\Response\ModelResponse;

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