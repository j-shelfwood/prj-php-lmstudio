<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Contract;

interface ApiClientInterface
{
    /**
     * Send a GET request to the API.
     *
     * @param  string  $endpoint  API endpoint
     * @param  array  $headers  Additional headers
     * @return array Response data
     *
     * @throws \Shelfwood\LMStudio\Exception\ApiException If the request fails
     */
    public function get(string $endpoint, array $headers = []): array;

    /**
     * Send a POST request to the API.
     *
     * @param  string  $endpoint  API endpoint
     * @param  array  $data  Request data
     * @param  array  $headers  Additional headers
     * @return array Response data
     *
     * @throws \Shelfwood\LMStudio\Exception\ApiException If the request fails
     */
    public function post(string $endpoint, array $data, array $headers = []): array;

    /**
     * Send a streaming POST request to the API.
     *
     * @param  string  $endpoint  API endpoint
     * @param  array  $data  Request data
     * @param  callable  $callback  Callback function to handle each chunk of data
     * @param  array  $headers  Additional headers
     *
     * @throws \Shelfwood\LMStudio\Exception\ApiException If the request fails
     */
    public function postStream(string $endpoint, array $data, callable $callback, array $headers = []): void;
}
