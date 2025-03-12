<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Contract;

interface ServiceInterface
{
    /**
     * Get the API client.
     *
     * @return ApiClientInterface
     */
    public function getApiClient(): ApiClientInterface;
}