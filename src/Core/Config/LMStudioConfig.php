<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Config;

use Shelfwood\LMStudio\Exception\InvalidConfigurationException;
use Shelfwood\LMStudio\Log\Logger;

class LMStudioConfig
{
    /**
     * @var array<string, mixed> Debug configuration
     */
    private array $debugConfig;

    /**
     * @var Logger The logger instance
     */
    private Logger $logger;

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
     * @param  int|null  $ttl  Time-To-Live for models in seconds (null means no TTL)
     * @param  bool|null  $autoEvict  Whether to automatically evict models when loading new ones
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
        private ?int $ttl = null,
        private ?bool $autoEvict = true,
        array $debugConfig = []
    ) {
        if ($ttl !== null && $ttl < 0) {
            throw new InvalidConfigurationException('TTL must be a non-negative integer');
        }

        $this->debugConfig = array_merge([
            'enabled' => (bool) getenv('LMSTUDIO_DEBUG'),
            'verbose' => (bool) getenv('LMSTUDIO_DEBUG_VERBOSE'),
            'log_file' => getenv('LMSTUDIO_DEBUG_LOG') ?: null,
        ], $debugConfig);

        // Initialize logger
        $this->logger = Logger::fromConfig($this->debugConfig);
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
     *
     * @return array<string, string> The headers
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
     * Get the Time-To-Live (TTL) for models in seconds.
     */
    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    /**
     * Check if auto-evict is enabled.
     */
    public function isAutoEvictEnabled(): ?bool
    {
        return $this->autoEvict;
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
     * Get the logger instance.
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * Create a new instance with a different base URL.
     *
     * @param  string  $baseUrl  The new base URL
     * @return self A new instance with the updated configuration
     *
     * @throws InvalidConfigurationException If the URL is invalid
     */
    public function withBaseUrl(string $baseUrl): self
    {
        if (empty($baseUrl)) {
            throw new InvalidConfigurationException('Base URL cannot be empty');
        }

        $clone = clone $this;
        $clone->baseUrl = $baseUrl;

        return $clone;
    }

    /**
     * Create a new instance with a different API key.
     *
     * @param  string  $apiKey  The new API key
     * @return self A new instance with the updated configuration
     *
     * @throws InvalidConfigurationException If the API key is invalid
     */
    public function withApiKey(string $apiKey): self
    {
        if (empty($apiKey)) {
            throw new InvalidConfigurationException('API key cannot be empty');
        }

        $clone = clone $this;
        $clone->apiKey = $apiKey;

        return $clone;
    }

    /**
     * Create a new instance with different headers.
     *
     * @param  array<string, string>  $headers  The new headers
     * @return self A new instance with the updated configuration
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = $headers;

        return $clone;
    }

    /**
     * Create a new instance with a different timeout.
     *
     * @param  int  $timeout  The new timeout in seconds
     * @return self A new instance with the updated configuration
     *
     * @throws InvalidConfigurationException If the timeout is invalid
     */
    public function withTimeout(int $timeout): self
    {
        if ($timeout <= 0) {
            throw new InvalidConfigurationException('Timeout must be greater than zero');
        }

        $clone = clone $this;
        $clone->timeout = $timeout;

        return $clone;
    }

    /**
     * Create a new instance with a different default model.
     *
     * @param  string|null  $defaultModel  The new default model
     * @return self A new instance with the updated configuration
     */
    public function withDefaultModel(?string $defaultModel): self
    {
        $clone = clone $this;
        $clone->defaultModel = $defaultModel;

        return $clone;
    }

    /**
     * Create a new instance with the given connection timeout.
     *
     * @param  int|null  $connectTimeout  The new connection timeout in seconds
     * @return self A new instance with the updated configuration
     *
     * @throws InvalidConfigurationException If the timeout is invalid
     */
    public function withConnectTimeout(?int $connectTimeout): self
    {
        if ($connectTimeout !== null && $connectTimeout <= 0) {
            throw new InvalidConfigurationException('Connection timeout must be greater than zero');
        }

        $clone = clone $this;
        $clone->connectTimeout = $connectTimeout;

        return $clone;
    }

    /**
     * Create a new instance with the given idle timeout.
     *
     * @param  int|null  $idleTimeout  The new idle timeout in seconds
     * @return self A new instance with the updated configuration
     *
     * @throws InvalidConfigurationException If the timeout is invalid
     */
    public function withIdleTimeout(?int $idleTimeout): self
    {
        if ($idleTimeout !== null && $idleTimeout <= 0) {
            throw new InvalidConfigurationException('Idle timeout must be greater than zero');
        }

        $clone = clone $this;
        $clone->idleTimeout = $idleTimeout;

        return $clone;
    }

    /**
     * Create a new instance with the given max retries.
     *
     * @param  int|null  $maxRetries  The new maximum number of retry attempts
     * @return self A new instance with the updated configuration
     *
     * @throws InvalidConfigurationException If the value is invalid
     */
    public function withMaxRetries(?int $maxRetries): self
    {
        if ($maxRetries !== null && $maxRetries < 0) {
            throw new InvalidConfigurationException('Max retries must be a non-negative integer');
        }

        $clone = clone $this;
        $clone->maxRetries = $maxRetries;

        return $clone;
    }

    /**
     * Create a new instance with health check enabled/disabled.
     *
     * @param  bool|null  $enabled  Whether to enable health checks
     * @return self A new instance with the updated configuration
     */
    public function withHealthCheckEnabled(?bool $enabled): self
    {
        $clone = clone $this;
        $clone->healthCheckEnabled = $enabled;

        return $clone;
    }

    /**
     * Create a new instance with a different TTL (Time-To-Live) for models.
     *
     * @param  int|null  $ttl  The TTL in seconds (null means no TTL)
     * @return self A new instance with the updated configuration
     *
     * @throws InvalidConfigurationException If the TTL is invalid
     */
    public function withTtl(?int $ttl): self
    {
        if ($ttl !== null && $ttl < 0) {
            throw new InvalidConfigurationException('TTL must be a non-negative integer');
        }

        $clone = clone $this;
        $clone->ttl = $ttl;

        return $clone;
    }

    /**
     * Create a new instance with auto-evict enabled or disabled.
     *
     * @param  bool|null  $autoEvict  Whether to enable auto-evict
     * @return self A new instance with the updated configuration
     */
    public function withAutoEvict(?bool $autoEvict): self
    {
        $clone = clone $this;
        $clone->autoEvict = $autoEvict;

        return $clone;
    }

    /**
     * Create a new instance with the given debug configuration.
     *
     * @param  array<string, mixed>  $debugConfig  The new debug configuration
     * @return self A new instance with the updated configuration
     */
    public function withDebugConfig(array $debugConfig): self
    {
        $clone = clone $this;
        $clone->debugConfig = array_merge($this->debugConfig, $debugConfig);
        $clone->logger = Logger::fromConfig($clone->debugConfig);

        return $clone;
    }
}
