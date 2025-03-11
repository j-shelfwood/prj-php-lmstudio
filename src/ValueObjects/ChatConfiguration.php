<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObjects;

class ChatConfiguration implements \JsonSerializable
{
    /**
     * Create a new chat configuration.
     */
    public function __construct(
        private string $model,
        private float $temperature = 0.7,
        private int $maxTokens = 4000,
        private bool $streaming = false,
        private bool $tools = false,
        private string $systemMessage = 'You are a helpful assistant.',
        private array $metadata = [],
    ) {}

    /**
     * Create a new chat configuration with default values.
     */
    public static function default(string $model = 'qwen2.5-7b-instruct-1m'): self
    {
        return new self($model);
    }

    /**
     * Create a new chat configuration builder.
     */
    public static function builder(string $model = 'qwen2.5-7b-instruct-1m'): ChatConfigurationBuilder
    {
        return new ChatConfigurationBuilder($model);
    }

    /**
     * Get the model.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the temperature.
     */
    public function getTemperature(): float
    {
        return $this->temperature;
    }

    /**
     * Get the maximum number of tokens.
     */
    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    /**
     * Check if streaming is enabled.
     */
    public function isStreaming(): bool
    {
        return $this->streaming;
    }

    /**
     * Check if tools are enabled.
     */
    public function hasTools(): bool
    {
        return $this->tools;
    }

    /**
     * Get the system message.
     */
    public function getSystemMessage(): string
    {
        return $this->systemMessage;
    }

    /**
     * Get the metadata.
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get a specific metadata value.
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Convert the configuration to an array.
     */
    public function jsonSerialize(): array
    {
        return [
            'model' => $this->model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'streaming' => $this->streaming,
            'tools' => $this->tools,
            'system_message' => $this->systemMessage,
            'metadata' => $this->metadata,
        ];
    }
}

/**
 * Builder for creating ChatConfiguration instances with a fluent API.
 */
class ChatConfigurationBuilder
{
    private string $model;

    private float $temperature = 0.7;

    private int $maxTokens = 4000;

    private bool $streaming = false;

    private bool $tools = false;

    private string $systemMessage = 'You are a helpful assistant.';

    private array $metadata = [];

    /**
     * Create a new chat configuration builder.
     */
    public function __construct(string $model = 'qwen2.5-7b-instruct-1m')
    {
        $this->model = $model;
    }

    /**
     * Set the model.
     */
    public function withModel(string $model): self
    {
        $clone = clone $this;
        $clone->model = $model;

        return $clone;
    }

    /**
     * Set the temperature.
     */
    public function withTemperature(float $temperature): self
    {
        $clone = clone $this;
        $clone->temperature = $temperature;

        return $clone;
    }

    /**
     * Set the maximum number of tokens.
     */
    public function withMaxTokens(int $maxTokens): self
    {
        $clone = clone $this;
        $clone->maxTokens = $maxTokens;

        return $clone;
    }

    /**
     * Enable or disable streaming.
     */
    public function withStreaming(bool $streaming = true): self
    {
        $clone = clone $this;
        $clone->streaming = $streaming;

        return $clone;
    }

    /**
     * Enable or disable tools.
     */
    public function withTools(bool $tools = true): self
    {
        $clone = clone $this;
        $clone->tools = $tools;

        return $clone;
    }

    /**
     * Set the system message.
     */
    public function withSystemMessage(string $systemMessage): self
    {
        $clone = clone $this;
        $clone->systemMessage = $systemMessage;

        return $clone;
    }

    /**
     * Set metadata.
     */
    public function withMetadata(array $metadata): self
    {
        $clone = clone $this;
        $clone->metadata = $metadata;

        return $clone;
    }

    /**
     * Add a metadata value.
     */
    public function withMetadataValue(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->metadata[$key] = $value;

        return $clone;
    }

    /**
     * Build the chat configuration.
     */
    public function build(): ChatConfiguration
    {
        return new ChatConfiguration(
            $this->model,
            $this->temperature,
            $this->maxTokens,
            $this->streaming,
            $this->tools,
            $this->systemMessage,
            $this->metadata
        );
    }
}
