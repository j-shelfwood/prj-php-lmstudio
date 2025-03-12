<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Service;

use Shelfwood\LMStudio\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Contract\ServiceInterface;

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