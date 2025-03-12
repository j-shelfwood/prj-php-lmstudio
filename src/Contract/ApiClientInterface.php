<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Contract;

interface ApiClientInterface
{
    /**
     * Send a GET request to the API.
     *
     * @param string $endpoint API endpoint
     * @param array $headers Additional headers
     * @return array Response data
     * @throws \Shelfwood\LMStudio\Exception\ApiException If the request fails
     */
    public function get(string $endpoint, array $headers = []): array;

    /**
     * Send a POST request to the API.
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param array $headers Additional headers
     * @return array Response data
     * @throws \Shelfwood\LMStudio\Exception\ApiException If the request fails
     */
    public function post(string $endpoint, array $data, array $headers = []): array;
}