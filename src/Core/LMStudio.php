<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core;

use Psr\Log\LoggerInterface;
use Shelfwood\LMStudio\Api\Client\LMS;
use Shelfwood\LMStudio\Api\Client\OpenAI;
use Shelfwood\LMStudio\Api\Contract\ConfigAwareInterface;
use Shelfwood\LMStudio\Api\Contract\LMStudioClientInterface;
use Shelfwood\LMStudio\Chat\Conversation;
use Shelfwood\LMStudio\Chat\ConversationInterface;
use Shelfwood\LMStudio\Chat\ConversationManagerInterface;
use Shelfwood\LMStudio\Core\Config\LMStudioConfig;
use Shelfwood\LMStudio\Core\Container\ServiceContainer;
use Shelfwood\LMStudio\Exception\InvalidConfigurationException;
use Shelfwood\LMStudio\Stream\StreamBuilderInterface;
use Shelfwood\LMStudio\Tool\ToolRegistryInterface;

/**
 * Main entry point for the LM Studio PHP client.
 *
 * This class provides access to both the native LM Studio API (v0) and
 * the OpenAI compatibility API (v1), as well as conversation management
 * and tool support.
 *
 * Example usage:
 * ```php
 * $lmstudio = new LMStudio();
 * $response = $lmstudio->lms()->chatCompletion($request);
 * ```
 */
class LMStudio implements ConfigAwareInterface
{
    /**
     * The service container.
     */
    private ServiceContainer $container;

    /**
     * Create a new LM Studio client instance.
     *
     * @param  LMStudioConfig|null  $config  Optional configuration (will use defaults if not provided)
     * @param  ServiceContainer|null  $container  Optional service container (will create a new one if not provided)
     */
    public function __construct(?LMStudioConfig $config = null, ?ServiceContainer $container = null)
    {
        $this->container = $container ?? new ServiceContainer;

        if ($config !== null) {
            $this->container->withConfig($config);
        }
    }

    /**
     * Get the client configuration.
     *
     * @return LMStudioConfig The current configuration
     */
    public function getConfig(): LMStudioConfig
    {
        return $this->container->get(LMStudioConfig::class);
    }

    /**
     * Get the service container.
     *
     * @return ServiceContainer The service container
     */
    public function getContainer(): ServiceContainer
    {
        return $this->container;
    }

    /**
     * Get the native LMStudio API client (v0).
     *
     * @return LMStudioClientInterface The LMS client instance
     */
    public function lms(): LMStudioClientInterface
    {
        $this->container->useClient(LMS::class);

        return $this->container->get(LMStudioClientInterface::class);
    }

    /**
     * Set the LMS client instance.
     *
     * @param  LMS  $client  The client instance to use
     * @return self For method chaining
     */
    public function setLmsClient(LMS $client): self
    {
        $this->container->instance(LMS::class, $client);
        $this->container->useClient(LMS::class);

        return $this;
    }

    /**
     * Get the OpenAI compatibility API client (v1).
     *
     * @return LMStudioClientInterface The OpenAI client instance
     */
    public function openai(): LMStudioClientInterface
    {
        $this->container->useClient(OpenAI::class);

        return $this->container->get(LMStudioClientInterface::class);
    }

    /**
     * Set the OpenAI client instance.
     *
     * @param  OpenAI  $client  The client instance to use
     * @return self For method chaining
     */
    public function setOpenAiClient(OpenAI $client): self
    {
        $this->container->instance(OpenAI::class, $client);
        $this->container->useClient(OpenAI::class);

        return $this;
    }

    /**
     * Set a logger for the client.
     *
     * @param  LoggerInterface  $logger  The logger to use
     * @return self For method chaining
     */
    public function withLogger(LoggerInterface $logger): self
    {
        $this->container->withLogger($logger);

        return $this;
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

        $config = $this->getConfig()->withBaseUrl($baseUrl);

        return new self($config);
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

        $config = $this->getConfig()->withApiKey($apiKey);

        return new self($config);
    }

    /**
     * Create a new instance with different headers.
     *
     * @param  array<string, string>  $headers  The new headers
     * @return self A new instance with the updated configuration
     */
    public function withHeaders(array $headers): self
    {
        $config = $this->getConfig()->withHeaders($headers);

        return new self($config);
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

        $config = $this->getConfig()->withTimeout($timeout);

        return new self($config);
    }

    /**
     * Create a new instance with a different TTL (Time-To-Live) for models.
     *
     * @param  int  $ttl  The TTL in seconds
     * @return self A new instance with the updated configuration
     *
     * @throws InvalidConfigurationException If the TTL is invalid
     */
    public function withTtl(int $ttl): self
    {
        if ($ttl < 0) {
            throw new InvalidConfigurationException('TTL must be a non-negative integer');
        }

        $config = $this->getConfig()->withTtl($ttl);

        return new self($config);
    }

    /**
     * Create a new instance with auto-evict enabled or disabled.
     *
     * @param  bool  $autoEvict  Whether to enable auto-evict
     * @return self A new instance with the updated configuration
     */
    public function withAutoEvict(bool $autoEvict): self
    {
        $config = $this->getConfig()->withAutoEvict($autoEvict);

        return new self($config);
    }

    /**
     * Create a new chat builder for streaming.
     *
     * @return StreamBuilderInterface A new stream builder instance
     */
    public function chat(): StreamBuilderInterface
    {
        return $this->container->get(StreamBuilderInterface::class);
    }

    /**
     * Get the conversation manager.
     *
     * @return ConversationManagerInterface The conversation manager instance
     */
    public function conversations(): ConversationManagerInterface
    {
        return $this->container->get(ConversationManagerInterface::class);
    }

    /**
     * Create a new conversation.
     *
     * @param  string  $title  The conversation title
     * @return ConversationInterface A new conversation instance
     */
    public function createConversation(string $title = 'New Conversation'): ConversationInterface
    {
        return $this->conversations()->createConversation($title);
    }

    /**
     * Create a new conversation with a system message.
     *
     * @param  string  $systemMessage  The system message content
     * @param  string  $title  The conversation title
     * @return ConversationInterface A new conversation instance with the system message
     */
    public function createConversationWithSystem(string $systemMessage, string $title = 'New Conversation'): ConversationInterface
    {
        return $this->conversations()->createConversationWithSystem($systemMessage, $title);
    }

    /**
     * Create a new conversation with tools.
     *
     * @param  ToolRegistryInterface  $toolRegistry  The tool registry
     * @param  string  $title  The conversation title
     * @param  string|null  $systemMessage  Optional system message content
     * @return ConversationInterface A new conversation instance with tools
     */
    public function createConversationWithTools(
        ToolRegistryInterface $toolRegistry,
        string $title = 'New Conversation',
        ?string $systemMessage = null
    ): ConversationInterface {
        return $this->conversations()->createConversationWithTools($toolRegistry, $title, $systemMessage);
    }

    /**
     * Create a new tool registry.
     *
     * @return ToolRegistryInterface A new tool registry instance
     */
    public function createToolRegistry(): ToolRegistryInterface
    {
        return $this->container->get(ToolRegistryInterface::class);
    }
}
