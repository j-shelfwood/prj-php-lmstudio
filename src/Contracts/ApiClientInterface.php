<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Contracts;

use Psr\Http\Message\ResponseInterface;

interface ApiClientInterface
{
    /**
     * Send a GET request
     *
     * @return array<string, mixed>
     */
    public function get(string $uri, array $options = []): array;

    /**
     * Send a POST request
     *
     * @return array<string, mixed>
     */
    public function post(string $uri, array $options = []): array;

    /**
     * Send a POST request with streaming response
     */
    public function postStreaming(string $uri, array $options = []): ResponseInterface;

    /**
     * Get the underlying HTTP client
     */
    public function getClient(): mixed;
}
