<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Chat;

use Shelfwood\LMStudio\Api\Contract\LMStudioClientInterface;
use Shelfwood\LMStudio\Http\Factory\RequestFactoryInterface;
use Shelfwood\LMStudio\Tool\ToolRegistry;
use Shelfwood\LMStudio\ValueObject\ChatHistory;

/**
 * Builder for creating Conversation instances with a fluent API.
 */
class ConversationBuilder
{
    private string $title = 'New Conversation';

    private ?string $id = null;

    private ?ChatHistory $history = null;

    private ?ToolRegistry $toolRegistry = null;

    private ?string $model = null;

    private ?float $temperature = null;

    private ?int $maxTokens = null;

    private array $metadata = [];

    private array $systemMessages = [];

    private array $userMessages = [];

    /**
     * Create a new conversation builder.
     */
    public function __construct(
        private LMStudioClientInterface $client,
        private ?RequestFactoryInterface $requestFactory = null
    ) {}

    /**
     * Set the conversation title.
     */
    public function withTitle(string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    /**
     * Set a custom ID for the conversation.
     */
    public function withId(string $id): self
    {
        $clone = clone $this;
        $clone->id = $id;

        return $clone;
    }

    /**
     * Set an existing chat history.
     */
    public function withHistory(ChatHistory $history): self
    {
        $clone = clone $this;
        $clone->history = $history;

        return $clone;
    }

    /**
     * Set the tool registry for the conversation.
     */
    public function withToolRegistry(ToolRegistry $toolRegistry): self
    {
        $clone = clone $this;
        $clone->toolRegistry = $toolRegistry;

        return $clone;
    }

    /**
     * Set the model to use for the conversation.
     */
    public function withModel(string $model): self
    {
        $clone = clone $this;
        $clone->model = $model;

        return $clone;
    }

    /**
     * Set the temperature for the conversation.
     */
    public function withTemperature(float $temperature): self
    {
        $clone = clone $this;
        $clone->temperature = $temperature;

        return $clone;
    }

    /**
     * Set the maximum number of tokens for the conversation.
     */
    public function withMaxTokens(int $maxTokens): self
    {
        $clone = clone $this;
        $clone->maxTokens = $maxTokens;

        return $clone;
    }

    /**
     * Set metadata for the conversation.
     */
    public function withMetadata(string|array $key, mixed $value = null): self
    {
        $clone = clone $this;

        if (is_array($key)) {
            $clone->metadata = array_merge($clone->metadata, $key);
        } else {
            $clone->metadata[$key] = $value;
        }

        return $clone;
    }

    /**
     * Add a system message to the conversation.
     */
    public function withSystemMessage(string $content): self
    {
        $clone = clone $this;
        $clone->systemMessages[] = $content;

        return $clone;
    }

    /**
     * Add a user message to the conversation.
     */
    public function withUserMessage(string $content, ?string $name = null): self
    {
        $clone = clone $this;
        $clone->userMessages[] = ['content' => $content, 'name' => $name];

        return $clone;
    }

    /**
     * Build and return a new Conversation instance.
     */
    public function build(): Conversation
    {
        // Create the conversation
        $conversation = new Conversation(
            $this->client,
            $this->title,
            $this->id,
            $this->history,
            null,
            $this->requestFactory
        );

        // Set model, temperature, and max tokens if provided
        if ($this->model !== null) {
            $conversation->setModel($this->model);
        }

        if ($this->temperature !== null) {
            $conversation->setTemperature($this->temperature);
        }

        if ($this->maxTokens !== null) {
            $conversation->setMaxTokens($this->maxTokens);
        }

        // Set tool registry if provided
        if ($this->toolRegistry !== null) {
            $conversation->setToolRegistry($this->toolRegistry);
        }

        // Set metadata if provided
        if (! empty($this->metadata)) {
            foreach ($this->metadata as $key => $value) {
                $conversation->setMetadata($key, $value);
            }
        }

        // Add system messages
        foreach ($this->systemMessages as $message) {
            $conversation->addSystemMessage($message);
        }

        // Add user messages
        foreach ($this->userMessages as $message) {
            $conversation->addUserMessage($message['content'], $message['name']);
        }

        return $conversation;
    }
}
