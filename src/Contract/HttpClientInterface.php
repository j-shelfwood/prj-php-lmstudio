<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Contract;

interface HttpClientInterface
{
    /**
     * Send a request to the API.
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param array $headers Additional headers
     * @return array Response data
     * @throws \Shelfwood\LMStudio\Exception\ApiException If the request fails
     */
    public function request(string $method, string $endpoint, array $data = [], array $headers = []): array;
}