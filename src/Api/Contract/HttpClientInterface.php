<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Contract;

interface HttpClientInterface
{
    /**
     * Send a request to the API.
     *
     * @param  string  $method  HTTP method
     * @param  string  $endpoint  API endpoint
     * @param  array  $data  Request data
     * @param  array  $headers  Additional headers
     * @return array Response data
     *
     * @throws \Shelfwood\LMStudio\Exception\ApiException If the request fails
     */
    public function request(string $method, string $endpoint, array $data = [], array $headers = []): array;

    /**
     * Send a streaming request to the API.
     *
     * @param  string  $method  HTTP method
     * @param  string  $endpoint  API endpoint
     * @param  array  $data  Request data
     * @param  callable  $callback  Callback function to handle each chunk of data
     * @param  array  $headers  Additional headers
     *
     * @throws \Shelfwood\LMStudio\Exception\ApiException If the request fails
     */
    public function requestStream(string $method, string $endpoint, array $data, callable $callback, array $headers = []): void;
}
