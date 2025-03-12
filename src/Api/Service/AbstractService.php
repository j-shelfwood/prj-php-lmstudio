<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Service;

use Shelfwood\LMStudio\Api\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Api\Contract\ServiceInterface;

abstract class AbstractService implements ServiceInterface
{
    protected ApiClientInterface $apiClient;

    /**
     * @param ApiClientInterface $apiClient The API client
     */
    public function __construct(ApiClientInterface $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * Get the API client.
     *
     * @return ApiClientInterface
     */
    public function getApiClient(): ApiClientInterface
    {
        return $this->apiClient;
    }
}