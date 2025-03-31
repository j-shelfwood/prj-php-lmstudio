<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Contract;

use Shelfwood\LMStudio\Api\Exception\ApiException;
use Shelfwood\LMStudio\Api\Model\ChatCompletionChunk;

interface HttpClientInterface
{
    /**
     * Send a request to the API.
     *
     * @param  string  $method  HTTP method
     * @param  string  $endpoint  API endpoint
     * @param  array  $data  Request data
     * @param  array<string, string>  $headers  Additional headers
     * @return array Response data
     *
     * @throws ApiException If the request fails
     */
    public function request(string $method, string $endpoint, array $data = [], array $headers = []): array;

    /**
     * Send a streaming request to the API.
     *
     * @param  string  $method  HTTP method
     * @param  string  $endpoint  API endpoint
     * @param  array  $data  Request data
     * @param  callable(ChatCompletionChunk): void  $callback  Callback function to handle each parsed chunk
     * @param  array<string, string>  $headers  Additional headers
     *
     * @throws ApiException If the request fails
     */
    public function requestStream(string $method, string $endpoint, array $data, callable $callback, array $headers = []): void;
}
