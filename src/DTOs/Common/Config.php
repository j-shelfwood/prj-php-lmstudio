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
        );
    }

    private function validate(): void
    {
        if (empty($this->host)) {
            throw ValidationException::invalidConfig('Host cannot be empty');
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw ValidationException::invalidConfig(
                'Port must be between 1 and 65535',
                ['port' => $this->port]
            );
        }

        if ($this->timeout < 1) {
            throw ValidationException::invalidConfig(
                'Timeout must be greater than 0',
                ['timeout' => $this->timeout]
            );
        }

        if ($this->temperature < 0 || $this->temperature > 2) {
            throw ValidationException::invalidConfig(
                'Temperature must be between 0 and 2',
                ['temperature' => $this->temperature]
            );
        }

        if ($this->retryAttempts < 0) {
            throw ValidationException::invalidConfig(
                'Retry attempts must be greater than or equal to 0',
                ['retry_attempts' => $this->retryAttempts]
            );
        }

        if ($this->retryDelay < 0) {
            throw ValidationException::invalidConfig(
                'Retry delay must be greater than or equal to 0',
                ['retry_delay' => $this->retryDelay]
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
        ], fn ($value) => $value !== null);
    }
}
