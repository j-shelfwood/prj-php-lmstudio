<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Common;

use Shelfwood\LMStudio\Exceptions\ValidationException;

final readonly class Config
{
    public function __construct(
        public string $host = 'localhost',
        public int $port = 1234,
        public int $timeout = 30,
        public float $temperature = 0.7,
        public int $maxTokens = -1,
        public ?string $defaultModel = null,
        public int $retryAttempts = 3,
        public int $retryDelay = 100,
        public int $defaultTtl = 3600,
        public bool $autoEvict = true,
        public string $toolUseMode = 'native',
        public string $apiVersion = 'v1' // 'v1' for OpenAI compatibility, 'v0' for LM Studio native
    ) {
        $this->validate();
    }

    public static function fromArray(array $config): self
    {
        return new self(
            host: $config['host'] ?? 'localhost',
            port: $config['port'] ?? 1234,
            timeout: $config['timeout'] ?? 30,
            temperature: $config['temperature'] ?? 0.7,
            maxTokens: $config['max_tokens'] ?? -1,
            defaultModel: $config['default_model'] ?? null,
            retryAttempts: $config['retry_attempts'] ?? 3,
            retryDelay: $config['retry_delay'] ?? 100,
            defaultTtl: $config['default_ttl'] ?? 3600,
            autoEvict: $config['auto_evict'] ?? true,
            toolUseMode: $config['tool_use_mode'] ?? 'native',
            apiVersion: $config['api_version'] ?? 'v1'
        );
    }

    private function validate(): void
    {
        if (empty($this->host)) {
            throw ValidationException::invalidConfig('Host cannot be empty');
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw ValidationException::invalidPort(
                message: 'Port must be between 1 and 65535'
            );
        }

        if ($this->timeout < 0) {
            throw ValidationException::invalidTimeout(
                message: 'Timeout must be greater than or equal to 0'
            );
        }

        if ($this->temperature < 0 || $this->temperature > 2) {
            throw ValidationException::invalidTemperature(
                message: 'Temperature must be between 0 and 2'
            );
        }

        if ($this->retryAttempts < 0) {
            throw ValidationException::invalidRetryAttempts(
                message: 'Retry attempts must be greater than or equal to 0'
            );
        }

        if ($this->retryDelay < 0) {
            throw ValidationException::invalidRetryDelay(
                message: 'Retry delay must be greater than or equal to 0'
            );
        }

        if ($this->defaultTtl < 0) {
            throw ValidationException::invalidTtl(
                message: 'Default TTL must be greater than or equal to 0'
            );
        }

        if (! in_array($this->toolUseMode, ['native', 'default'], true)) {
            throw ValidationException::invalidToolUseMode(
                message: 'Tool use mode must be either "native" or "default"'
            );
        }

        if (! in_array($this->apiVersion, ['v0', 'v1'], true)) {
            throw ValidationException::invalidConfig(
                message: 'API version must be either "v0" or "v1"'
            );
        }
    }

    public function toArray(): array
    {
        return array_filter([
            'host' => $this->host,
            'port' => $this->port,
            'timeout' => $this->timeout,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'default_model' => $this->defaultModel,
            'retry_attempts' => $this->retryAttempts,
            'retry_delay' => $this->retryDelay,
            'default_ttl' => $this->defaultTtl,
            'auto_evict' => $this->autoEvict,
            'tool_use_mode' => $this->toolUseMode,
            'api_version' => $this->apiVersion,
        ], fn ($value) => $value !== null);
    }
}
