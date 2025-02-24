<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Contracts;

interface ApiClientInterface
{
    /**
     * Send a GET request
     */
    public function get(string $uri, array $options = []): mixed;

    /**
     * Send a POST request
     */
    public function post(string $uri, array $options = []): mixed;

    /**
     * Get the underlying HTTP client
     */
    public function getClient(): mixed;
}
