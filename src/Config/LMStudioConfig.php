<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Config;

class LMStudioConfig
{
    /**
     * @var array<string, mixed> Debug configuration
     */
    private array $debugConfig;

    /**
     * @param  string  $baseUrl  The base URL for the LMStudio API
     * @param  string  $apiKey  The API key (not required for LMStudio but kept for OpenAI compatibility)
     * @param  int  $timeout  Request timeout in seconds
     * @param  array<string, string>  $headers  Additional headers to send with requests
     * @param  string|null  $defaultModel  The default model to use for requests
     * @param  int|null  $connectTimeout  Connection timeout in seconds
     * @param  int|null  $idleTimeout  Idle timeout for streaming in seconds
     * @param  int|null  $maxRetries  Maximum number of retry attempts
     * @param  bool|null  $healthCheckEnabled  Whether to perform health checks
     * @param  array<string, mixed>  $debugConfig  Debug configuration options
     */
    public function __construct(
        private string $baseUrl = 'http://localhost:1234',
        private string $apiKey = 'lm-studio',
        private int $timeout = 30,
        private array $headers = [],
        private ?string $defaultModel = null,
        private ?int $connectTimeout = 10,
        private ?int $idleTimeout = 15,
        private ?int $maxRetries = 3,
        private ?bool $healthCheckEnabled = true,
        array $debugConfig = []
    ) {
        $this->debugConfig = array_merge([
            'enabled' => (bool) getenv('LMSTUDIO_DEBUG'),
            'verbose' => (bool) getenv('LMSTUDIO_DEBUG_VERBOSE'),
            'log_file' => getenv('LMSTUDIO_DEBUG_LOG') ?: null,
        ], $debugConfig);
    }

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
     * Get the connection timeout.
     */
    public function getConnectTimeout(): ?int
    {
        return $this->connectTimeout;
    }

    /**
     * Get the idle timeout for streaming.
     */
    public function getIdleTimeout(): ?int
    {
        return $this->idleTimeout;
    }

    /**
     * Get the maximum number of retry attempts.
     */
    public function getMaxRetries(): ?int
    {
        return $this->maxRetries;
    }

    /**
     * Check if health checks are enabled.
     */
    public function isHealthCheckEnabled(): ?bool
    {
        return $this->healthCheckEnabled;
    }

    /**
     * Get the debug configuration.
     *
     * @return array<string, mixed> The debug configuration
     */
    public function getDebugConfig(): array
    {
        return $this->debugConfig;
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

    /**
     * Create a new instance with the given connection timeout.
     */
    public function withConnectTimeout(?int $connectTimeout): self
    {
        $clone = clone $this;
        $clone->connectTimeout = $connectTimeout;

        return $clone;
    }

    /**
     * Create a new instance with the given idle timeout.
     */
    public function withIdleTimeout(?int $idleTimeout): self
    {
        $clone = clone $this;
        $clone->idleTimeout = $idleTimeout;

        return $clone;
    }

    /**
     * Create a new instance with the given max retries.
     */
    public function withMaxRetries(?int $maxRetries): self
    {
        $clone = clone $this;
        $clone->maxRetries = $maxRetries;

        return $clone;
    }

    /**
     * Create a new instance with health check enabled/disabled.
     */
    public function withHealthCheckEnabled(?bool $enabled): self
    {
        $clone = clone $this;
        $clone->healthCheckEnabled = $enabled;

        return $clone;
    }

    /**
     * Create a new instance with the given debug configuration.
     *
     * @param  array<string, mixed>  $debugConfig  The debug configuration
     */
    public function withDebugConfig(array $debugConfig): self
    {
        $clone = clone $this;
        $clone->debugConfig = array_merge($this->debugConfig, $debugConfig);

        return $clone;
    }
}
