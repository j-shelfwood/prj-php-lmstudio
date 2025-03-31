<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Contract;

use Shelfwood\LMStudio\Api\Exception\ApiException;
use Shelfwood\LMStudio\Api\Model\ChatCompletionChunk;

interface ApiClientInterface
{
    /**
     * Send a GET request to the API.
     *
     * @param  string  $endpoint  API endpoint
     * @param  array<string, string>  $headers  Additional headers
     * @return array Response data
     *
     * @throws ApiException If the request fails
     */
    public function get(string $endpoint, array $headers = []): array;

    /**
     * Send a POST request to the API.
     *
     * @param  string  $endpoint  API endpoint
     * @param  array  $data  Request data
     * @param  array<string, string>  $headers  Additional headers
     * @return array Response data
     *
     * @throws ApiException If the request fails
     */
    public function post(string $endpoint, array $data, array $headers = []): array;

    /**
     * Send a streaming POST request to the API.
     *
     * @param  string  $endpoint  API endpoint
     * @param  array  $data  Request data
     * @param  callable(ChatCompletionChunk): void  $callback  Callback function to handle each parsed chunk
     * @param  array<string, string>  $headers  Additional headers
     *
     * @throws ApiException If the request fails
     */
    public function postStream(string $endpoint, array $data, callable $callback, array $headers = []): void;
}
