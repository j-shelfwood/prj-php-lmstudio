<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio;

use Shelfwood\LMStudio\Builders\StreamBuilder;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Contracts\ConfigAwareInterface;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Conversations\Conversation;
use Shelfwood\LMStudio\Conversations\ConversationManager;
use Shelfwood\LMStudio\Exceptions\InvalidConfigurationException;
use Shelfwood\LMStudio\Tools\ToolRegistry;

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
    private LMStudioConfig $config;

    private ?LMS $lms = null;

    private ?OpenAI $openai = null;

    private ?ConversationManager $conversationManager = null;

    /**
     * Create a new LM Studio client instance.
     *
     * @param  LMStudioConfig|null  $config  Optional configuration (will use defaults if not provided)
     */
    public function __construct(?LMStudioConfig $config = null)
    {
        $this->config = $config ?? new LMStudioConfig;
    }

    /**
     * Get the client configuration.
     *
     * @return LMStudioConfig The current configuration
     */
    public function getConfig(): LMStudioConfig
    {
        return $this->config;
    }

    /**
     * Get the native LMStudio API client (v0).
     *
     * @return LMStudioClientInterface The LMS client instance
     */
    public function lms(): LMStudioClientInterface
    {
        if ($this->lms === null) {
            $this->lms = new LMS($this->config);
        }

        return $this->lms;
    }

    /**
     * Set the LMS client instance.
     *
     * @param  LMS  $client  The client instance to use
     * @return self For method chaining
     */
    public function setLmsClient(LMS $client): self
    {
        $this->lms = $client;
        $this->conversationManager = null; // Reset conversation manager if client changes

        return $this;
    }

    /**
     * Get the OpenAI compatibility API client (v1).
     *
     * @return LMStudioClientInterface The OpenAI client instance
     */
    public function openai(): LMStudioClientInterface
    {
        if ($this->openai === null) {
            $this->openai = new OpenAI($this->config);
        }

        return $this->openai;
    }

    /**
     * Set the OpenAI client instance.
     *
     * @param  OpenAI  $client  The client instance to use
     * @return self For method chaining
     */
    public function setOpenAiClient(OpenAI $client): self
    {
        $this->openai = $client;
        $this->conversationManager = null; // Reset conversation manager if client changes

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

        $clone = clone $this;
        $clone->config = $this->config->withBaseUrl($baseUrl);
        $clone->lms = null;
        $clone->openai = null;
        $clone->conversationManager = null;

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
        $clone->config = $this->config->withApiKey($apiKey);
        $clone->lms = null;
        $clone->openai = null;
        $clone->conversationManager = null;

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
        $clone->config = $this->config->withHeaders($headers);
        $clone->lms = null;
        $clone->openai = null;
        $clone->conversationManager = null;

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
        $clone->config = $this->config->withTimeout($timeout);
        $clone->lms = null;
        $clone->openai = null;
        $clone->conversationManager = null;

        return $clone;
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

        $clone = clone $this;
        $clone->config = $this->config->withTtl($ttl);
        $clone->lms = null;
        $clone->openai = null;
        $clone->conversationManager = null;

        return $clone;
    }

    /**
     * Create a new instance with auto-evict enabled or disabled.
     *
     * @param  bool  $autoEvict  Whether to enable auto-evict
     * @return self A new instance with the updated configuration
     */
    public function withAutoEvict(bool $autoEvict): self
    {
        $clone = clone $this;
        $clone->config = $this->config->withAutoEvict($autoEvict);
        $clone->lms = null;
        $clone->openai = null;
        $clone->conversationManager = null;

        return $clone;
    }

    /**
     * Create a new chat builder for streaming.
     *
     * @return StreamBuilder A new stream builder instance
     */
    public function chat(): StreamBuilder
    {
        return new StreamBuilder($this->lms());
    }

    /**
     * Get the conversation manager.
     *
     * @return ConversationManager The conversation manager instance
     */
    public function conversations(): ConversationManager
    {
        if ($this->conversationManager === null) {
            $this->conversationManager = new ConversationManager($this->lms());
        }

        return $this->conversationManager;
    }

    /**
     * Create a new conversation.
     *
     * @param  string  $title  The conversation title
     * @return Conversation A new conversation instance
     */
    public function createConversation(string $title = 'New Conversation'): Conversation
    {
        return $this->conversations()->createConversation($title);
    }

    /**
     * Create a new conversation with a system message.
     *
     * @param  string  $systemMessage  The system message content
     * @param  string  $title  The conversation title
     * @return Conversation A new conversation instance with the system message
     */
    public function createConversationWithSystem(string $systemMessage, string $title = 'New Conversation'): Conversation
    {
        return $this->conversations()->createConversationWithSystem($systemMessage, $title);
    }

    /**
     * Create a new conversation with tools.
     *
     * @param  ToolRegistry  $toolRegistry  The tool registry to use
     * @param  string  $title  The conversation title
     * @param  string|null  $systemMessage  Optional system message
     * @return Conversation A new conversation instance with tools
     */
    public function createConversationWithTools(
        ToolRegistry $toolRegistry,
        string $title = 'New Conversation',
        ?string $systemMessage = null
    ): Conversation {
        return $this->conversations()->createConversationWithTools($toolRegistry, $title, $systemMessage);
    }

    /**
     * Create a new tool registry.
     *
     * @return ToolRegistry A new tool registry instance
     */
    public function createToolRegistry(): ToolRegistry
    {
        return new ToolRegistry;
    }
}
