<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio;

use Shelfwood\LMStudio\Builders\StreamBuilder;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Contracts\ConfigAwareInterface;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Conversations\Conversation;
use Shelfwood\LMStudio\Conversations\ConversationManager;
use Shelfwood\LMStudio\Tools\ToolRegistry;

class LMStudio implements ConfigAwareInterface
{
    private LMStudioConfig $config;

    private ?LMS $lms = null;

    private ?OpenAI $openai = null;

    private ?ConversationManager $conversationManager = null;

    public function __construct(?LMStudioConfig $config = null)
    {
        $this->config = $config ?? new LMStudioConfig;
    }

    /**
     * Get the client configuration.
     */
    public function getConfig(): LMStudioConfig
    {
        return $this->config;
    }

    /**
     * Get the native LMStudio API client (v0).
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
     */
    public function setLmsClient(LMS $client): self
    {
        $this->lms = $client;
        $this->conversationManager = null; // Reset conversation manager if client changes

        return $this;
    }

    /**
     * Get the OpenAI compatibility API client (v1).
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
     */
    public function setOpenAiClient(OpenAI $client): self
    {
        $this->openai = $client;
        $this->conversationManager = null; // Reset conversation manager if client changes

        return $this;
    }

    /**
     * Create a new instance with a different base URL.
     */
    public function withBaseUrl(string $baseUrl): self
    {
        $clone = clone $this;
        $clone->config = $this->config->withBaseUrl($baseUrl);
        $clone->lms = null;
        $clone->openai = null;
        $clone->conversationManager = null;

        return $clone;
    }

    /**
     * Create a new instance with a different API key.
     */
    public function withApiKey(string $apiKey): self
    {
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
     * @param  array<string, string>  $headers
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
     */
    public function withTimeout(int $timeout): self
    {
        $clone = clone $this;
        $clone->config = $this->config->withTimeout($timeout);
        $clone->lms = null;
        $clone->openai = null;
        $clone->conversationManager = null;

        return $clone;
    }

    /**
     * Create a new chat builder for streaming.
     */
    public function chat(): StreamBuilder
    {
        return new StreamBuilder($this->lms());
    }

    /**
     * Get the conversation manager.
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
     */
    public function createConversation(string $title = 'New Conversation'): Conversation
    {
        return $this->conversations()->createConversation($title);
    }

    /**
     * Create a new conversation with a system message.
     */
    public function createConversationWithSystem(string $systemMessage, string $title = 'New Conversation'): Conversation
    {
        return $this->conversations()->createConversationWithSystem($systemMessage, $title);
    }

    /**
     * Create a new conversation with tools.
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
     */
    public function createToolRegistry(): ToolRegistry
    {
        return new ToolRegistry;
    }
}
