<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Config;

class LMStudioConfig
{
    /**
     * @param  string  $baseUrl  The base URL for the LMStudio API
     * @param  string  $apiKey  The API key (not required for LMStudio but kept for OpenAI compatibility)
     * @param  int  $timeout  Request timeout in seconds
     * @param  array<string, string>  $headers  Additional headers to send with requests
     * @param  string|null  $defaultModel  The default model to use for requests
     */
    public function __construct(
        private string $baseUrl = 'http://localhost:1234',
        private string $apiKey = 'lm-studio',
        private int $timeout = 30,
        private array $headers = [],
        private ?string $defaultModel = null
    ) {}

    /**
     * Get the base URL.
     */
    public function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * Get the API key.
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Get the timeout.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Get the default model.
     */
    public function getDefaultModel(): ?string
    {
        return $this->defaultModel;
    }

    /**
     * Get the headers.
     */
    public function getHeaders(): array
    {
        return array_merge([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->apiKey}",
        ], $this->headers);
    }

    /**
     * Create a new instance with a different base URL.
     */
    public function withBaseUrl(string $baseUrl): self
    {
        $clone = clone $this;
        $clone->baseUrl = $baseUrl;

        return $clone;
    }

    /**
     * Create a new instance with a different API key.
     */
    public function withApiKey(string $apiKey): self
    {
        $clone = clone $this;
        $clone->apiKey = $apiKey;

        return $clone;
    }

    /**
     * Create a new instance with different headers.
     *
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = $headers;

        return $clone;
    }

    /**
     * Create a new instance with a different timeout.
     */
    public function withTimeout(int $timeout): self
    {
        $clone = clone $this;
        $clone->timeout = $timeout;

        return $clone;
    }

    /**
     * Create a new instance with a different default model.
     */
    public function withDefaultModel(?string $defaultModel): self
    {
        $clone = clone $this;
        $clone->defaultModel = $defaultModel;

        return $clone;
    }
}
