<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Contract;

interface ServiceInterface
{
    /**
     * Get the API client.
     *
     * @return ApiClientInterface
     */
    public function getApiClient(): ApiClientInterface;
}