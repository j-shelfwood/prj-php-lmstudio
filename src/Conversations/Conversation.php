<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Conversations;

use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Http\Factories\RequestFactoryInterface;
use Shelfwood\LMStudio\Streaming\StreamBuilder;
use Shelfwood\LMStudio\Streaming\StreamBuilderInterface;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\ChatConfiguration;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;
use Shelfwood\LMStudio\ValueObjects\ToolCall;

/**
 * High-level API for working with conversations.
 */
class Conversation implements ConversationInterface
{
    protected ChatHistory $chatHistory;

    protected ?ChatConfiguration $configuration;

    protected ?ToolRegistry $toolRegistry = null;

    protected string $toolUseMode = 'auto';

    protected ?LMStudioClientInterface $client = null;

    protected ?RequestFactoryInterface $requestFactory = null;

    protected string $title = 'New Conversation';

    protected string $id;

    protected \DateTimeImmutable $createdAt;

    protected ?\DateTimeImmutable $updatedAt = null;

    protected array $metadata = [];

    /**
     * Create a new conversation.
     */
    public function __construct(
        ?LMStudioClientInterface $client = null,
        ?string $title = null,
        ?string $id = null,
        ?ChatHistory $history = null,
        ?ChatConfiguration $configuration = null,
        ?RequestFactoryInterface $requestFactory = null
    ) {
        $this->client = $client;
        $this->chatHistory = $history ?? new ChatHistory;
        $this->id = $id ?? 'conv_'.uniqid();
        $this->title = $title ?? 'New Conversation';
        $this->configuration = $configuration ?? ChatConfiguration::default();
        $this->createdAt = new \DateTimeImmutable;
        $this->requestFactory = $requestFactory;
    }

    /**
     * Create a conversation with a system message.
     */
    public static function withSystemMessage(
        LMStudioClientInterface $client,
        string $systemMessage,
        ?RequestFactoryInterface $requestFactory = null
    ): self {
        $configuration = ChatConfiguration::builder()
            ->withSystemMessage($systemMessage)
            ->build();

        $conversation = new self($client, null, null, null, $configuration, $requestFactory);
        $conversation->addSystemMessage($systemMessage);

        return $conversation;
    }

    /**
     * Create a conversation with tools.
     */
    public static function withTools(
        LMStudioClientInterface $client,
        ToolRegistry $toolRegistry,
        ?string $systemMessage = null,
        ?RequestFactoryInterface $requestFactory = null
    ): self {
        $configuration = ChatConfiguration::builder()
            ->withTools(true)
            ->withSystemMessage($systemMessage ?? 'You are a helpful assistant with tools.')
            ->build();

        $conversation = new self($client, null, null, null, $configuration, $requestFactory);

        if ($systemMessage !== null) {
            $conversation->addSystemMessage($systemMessage);
        }

        $conversation->withToolRegistry($toolRegistry);

        return $conversation;
    }

    /**
     * Create a conversation builder.
     */
    public static function builder(
        LMStudioClientInterface $client,
        ?RequestFactoryInterface $requestFactory = null
    ): ConversationBuilder {
        return new ConversationBuilder($client, $requestFactory);
    }

    /**
     * Add a system message to the conversation.
     */
    public function addSystemMessage(string $content): self
    {
        $this->chatHistory->addSystemMessage($content);

        return $this;
    }

    /**
     * Add a user message to the conversation.
     */
    public function addUserMessage(string $content, ?string $name = null): self
    {
        if ($name !== null) {
            $this->chatHistory->addUserMessage($content, $name);
        } else {
            $this->chatHistory->addUserMessage($content);
        }

        return $this;
    }

    /**
     * Add an assistant message to the conversation.
     */
    public function addAssistantMessage(string $content, array $toolCalls = []): self
    {
        $this->chatHistory->addAssistantMessage($content, $toolCalls);

        return $this;
    }

    /**
     * Add a tool message to the conversation.
     */
    public function addToolMessage(string $content, string $toolCallId): self
    {
        $this->chatHistory->addToolMessage($content, $toolCallId);

        return $this;
    }

    /**
     * Set the model to use for the conversation.
     */
    public function withModel(string $model): self
    {
        $this->configuration = ChatConfiguration::builder()
            ->withModel($model)
            ->withTemperature($this->configuration->getTemperature())
            ->withMaxTokens($this->configuration->getMaxTokens())
            ->withSystemMessage($this->configuration->getSystemMessage())
            ->withMetadata($this->configuration->getMetadata())
            ->withTools($this->configuration->hasTools())
            ->withStreaming($this->configuration->isStreaming())
            ->build();

        return $this;
    }

    /**
     * Set the model to use for the conversation (alias for backward compatibility).
     */
    public function setModel(string $model): self
    {
        return $this->withModel($model);
    }

    /**
     * Set the temperature to use for the conversation.
     */
    public function withTemperature(float $temperature): self
    {
        $this->configuration = ChatConfiguration::builder()
            ->withModel($this->configuration->getModel())
            ->withTemperature($temperature)
            ->withMaxTokens($this->configuration->getMaxTokens())
            ->withSystemMessage($this->configuration->getSystemMessage())
            ->withMetadata($this->configuration->getMetadata())
            ->withTools($this->configuration->hasTools())
            ->withStreaming($this->configuration->isStreaming())
            ->build();

        return $this;
    }

    /**
     * Set the temperature to use for the conversation (alias for backward compatibility).
     */
    public function setTemperature(float $temperature): self
    {
        return $this->withTemperature($temperature);
    }

    /**
     * Set the max tokens to use for the conversation.
     */
    public function withMaxTokens(int $maxTokens): self
    {
        $this->configuration = ChatConfiguration::builder()
            ->withModel($this->configuration->getModel())
            ->withTemperature($this->configuration->getTemperature())
            ->withMaxTokens($maxTokens)
            ->withSystemMessage($this->configuration->getSystemMessage())
            ->withMetadata($this->configuration->getMetadata())
            ->withTools($this->configuration->hasTools())
            ->withStreaming($this->configuration->isStreaming())
            ->build();

        return $this;
    }

    /**
     * Set the max tokens to use for the conversation (alias for backward compatibility).
     */
    public function setMaxTokens(int $maxTokens): self
    {
        return $this->withMaxTokens($maxTokens);
    }

    /**
     * Set the tool registry to use for the conversation.
     */
    public function withToolRegistry(ToolRegistry $toolRegistry): self
    {
        $this->toolRegistry = $toolRegistry;

        return $this;
    }

    /**
     * Set the tool registry to use for the conversation (alias for backward compatibility).
     */
    public function setToolRegistry(ToolRegistry $toolRegistry): self
    {
        return $this->withToolRegistry($toolRegistry);
    }

    /**
     * Set the tool use mode to use for the conversation.
     */
    public function withToolUseMode(string $mode): self
    {
        $this->toolUseMode = $mode;

        return $this;
    }

    /**
     * Get the chat history.
     */
    public function getChatHistory(): ChatHistory
    {
        return $this->chatHistory;
    }

    /**
     * Get all messages in the conversation.
     */
    public function getMessages(): array
    {
        return $this->chatHistory->getMessages();
    }

    /**
     * Get the last message in the conversation.
     */
    public function getLastMessage(): ?Message
    {
        $messages = $this->chatHistory->getMessages();

        return end($messages) ?: null;
    }

    /**
     * Get the conversation ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the conversation title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Set the conversation title.
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        $this->updatedAt = new \DateTimeImmutable;

        return $this;
    }

    /**
     * Get the creation timestamp.
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Get the last update timestamp.
     */
    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Get a metadata value.
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set a metadata value.
     */
    public function setMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        $this->updatedAt = new \DateTimeImmutable;

        return $this;
    }

    /**
     * Get all metadata.
     */
    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Check if the conversation has tools.
     */
    public function hasTools(): bool
    {
        return $this->toolRegistry !== null;
    }

    /**
     * Get the tool registry.
     */
    public function getToolRegistry(): ?ToolRegistry
    {
        return $this->toolRegistry;
    }

    /**
     * Get the model.
     */
    public function getModel(): ?string
    {
        return $this->configuration->getModel();
    }

    /**
     * Get the temperature.
     */
    public function getTemperature(): float
    {
        return $this->configuration->getTemperature();
    }

    /**
     * Get the max tokens.
     */
    public function getMaxTokens(): ?int
    {
        return $this->configuration->getMaxTokens();
    }

    /**
     * Create a stream builder for this conversation.
     */
    public function stream(): StreamBuilderInterface
    {
        $builder = new StreamBuilder($this->client, $this->requestFactory);
        $builder->withChatHistory($this->chatHistory);

        if ($this->configuration->getModel() !== null) {
            $builder->withModel($this->configuration->getModel());
        }

        $builder->withTemperature($this->configuration->getTemperature());

        if ($this->configuration->getMaxTokens() > 0) {
            $builder->withMaxTokens($this->configuration->getMaxTokens());
        }

        if ($this->toolRegistry !== null) {
            $builder->withToolRegistry($this->toolRegistry);
            $builder->withToolUseMode($this->toolUseMode);
        }

        return $builder;
    }

    /**
     * Send a message and get the response.
     */
    public function send(string $message, ?string $name = null): string
    {
        if ($this->client === null) {
            throw new \InvalidArgumentException('Client is required for send()');
        }

        // Add the user message
        $this->addUserMessage($message, $name);

        // Create a request object
        $apiVersion = $this->client->getApiVersionNamespace();
        $requestClass = "\\Shelfwood\\LMStudio\\Http\\Requests\\{$apiVersion}\\ChatCompletionRequest";

        // Create the request with required parameters
        $request = new $requestClass(
            $this->chatHistory,
            $this->configuration->getModel() ?? $this->client->getConfig()->getDefaultModel() ?? 'qwen2.5-7b-instruct-1m'
        );

        // Set additional parameters
        $request = $request->withTemperature($this->configuration->getTemperature());

        if ($this->configuration->getMaxTokens() > 0) {
            $request = $request->withMaxTokens($this->configuration->getMaxTokens());
        }

        // Add tools if available
        if ($this->toolRegistry !== null) {
            $request = $request->withTools($this->toolRegistry->getTools());
            $request = $request->withToolChoice($this->toolUseMode);
        }

        // Get the response
        $response = $this->client->chatCompletion($request);
        $content = $response->choices[0]->message->content ?? '';

        // Add the assistant's response to the conversation
        $this->addAssistantMessage($content);

        return $content;
    }

    /**
     * Get a response from the conversation.
     */
    public function getResponse(): string
    {
        if ($this->client === null) {
            throw new \InvalidArgumentException('Client is required for getResponse()');
        }

        // Create a request object
        $apiVersion = $this->client->getApiVersionNamespace();
        $requestClass = "\\Shelfwood\\LMStudio\\Http\\Requests\\{$apiVersion}\\ChatCompletionRequest";

        // Create the request with required parameters
        $request = new $requestClass(
            $this->chatHistory,
            $this->configuration->getModel() ?? $this->client->getConfig()->getDefaultModel() ?? 'qwen2.5-7b-instruct-1m'
        );

        // Set additional parameters
        $request = $request->withTemperature($this->configuration->getTemperature());

        if ($this->configuration->getMaxTokens() > 0) {
            $request = $request->withMaxTokens($this->configuration->getMaxTokens());
        }

        // Add tools if available
        if ($this->toolRegistry !== null) {
            $request = $request->withTools($this->toolRegistry->getTools());
            $request = $request->withToolChoice($this->toolUseMode);
        }

        // Get the initial response
        $response = $this->client->chatCompletion($request);
        $content = $response->choices[0]->message->content ?? '';
        $toolCalls = $response->choices[0]->message->tool_calls ?? [];

        // Add the assistant's response to the conversation
        if (! empty($toolCalls)) {
            $this->addAssistantMessage($content, $toolCalls);

            // Process tool calls
            foreach ($toolCalls as $toolCall) {
                if ($this->toolRegistry !== null) {
                    $toolCallId = $toolCall->id;
                    $toolName = $toolCall->function->name;
                    $toolArgs = json_decode($toolCall->function->arguments, true);

                    // Execute the tool
                    $result = $this->toolRegistry->execute(
                        ToolCall::function(
                            $toolName,
                            $toolCall->function->arguments,
                            $toolCallId
                        )
                    );

                    // Add the tool response to the conversation
                    $this->addToolMessage($result, $toolCallId);
                }
            }

            // Get the final response after tool execution
            $request = new $requestClass(
                $this->chatHistory,
                $this->configuration->getModel() ?? $this->client->getConfig()->getDefaultModel() ?? 'qwen2.5-7b-instruct-1m'
            );

            // Set additional parameters
            $request = $request->withTemperature($this->configuration->getTemperature());

            if ($this->configuration->getMaxTokens() > 0) {
                $request = $request->withMaxTokens($this->configuration->getMaxTokens());
            }

            // Add tools if available
            if ($this->toolRegistry !== null) {
                $request = $request->withTools($this->toolRegistry->getTools());
                $request = $request->withToolChoice($this->toolUseMode);
            }

            // Get the final response
            $response = $this->client->chatCompletion($request);
            $content = $response->choices[0]->message->content ?? '';

            // Add the assistant's final response to the conversation
            $this->addAssistantMessage($content);
        } else {
            $this->addAssistantMessage($content);
        }

        return $content;
    }

    /**
     * Convert the conversation to JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->jsonSerialize());
    }

    /**
     * Create a conversation from JSON.
     */
    public static function fromJson(
        string $json,
        LMStudioClientInterface $client,
        ?RequestFactoryInterface $requestFactory = null
    ): self {
        $data = json_decode($json, true);

        $conversation = new self(
            $client,
            $data['title'] ?? null,
            $data['id'] ?? null,
            null,
            null,
            $requestFactory
        );

        if (isset($data['messages']) && is_array($data['messages'])) {
            $conversation->chatHistory = ChatHistory::fromArray($data['messages']);
        }

        if (isset($data['model'])) {
            $conversation->configuration = ChatConfiguration::builder()
                ->withModel($data['model'])
                ->withTemperature($data['temperature'] ?? 0.7)
                ->withMaxTokens($data['max_tokens'] ?? null)
                ->withSystemMessage($data['system_message'] ?? null)
                ->withMetadata($data['metadata'] ?? [])
                ->withTools($data['has_tools'] ?? false)
                ->withStreaming($data['is_streaming'] ?? false)
                ->build();
        }

        if (isset($data['temperature'])) {
            $conversation->configuration = ChatConfiguration::builder()
                ->withModel($conversation->configuration->getModel())
                ->withTemperature($data['temperature'])
                ->withMaxTokens($conversation->configuration->getMaxTokens())
                ->withSystemMessage($conversation->configuration->getSystemMessage())
                ->withMetadata($conversation->configuration->getMetadata())
                ->withTools($conversation->configuration->hasTools())
                ->withStreaming($conversation->configuration->isStreaming())
                ->build();
        }

        if (isset($data['max_tokens'])) {
            $conversation->configuration = ChatConfiguration::builder()
                ->withModel($conversation->configuration->getModel())
                ->withTemperature($conversation->configuration->getTemperature())
                ->withMaxTokens($data['max_tokens'])
                ->withSystemMessage($conversation->configuration->getSystemMessage())
                ->withMetadata($conversation->configuration->getMetadata())
                ->withTools($conversation->configuration->hasTools())
                ->withStreaming($conversation->configuration->isStreaming())
                ->build();
        }

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $conversation->metadata = $data['metadata'];
        }

        return $conversation;
    }

    /**
     * Convert the conversation to an array.
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt ? $this->updatedAt->format('c') : null,
            'model' => $this->configuration->getModel(),
            'temperature' => $this->configuration->getTemperature(),
            'max_tokens' => $this->configuration->getMaxTokens(),
            'messages' => $this->chatHistory->jsonSerialize(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create a new conversation from a chat history.
     */
    public static function fromChatHistory(ChatHistory $chatHistory): self
    {
        $conversation = new self;
        $conversation->chatHistory = $chatHistory;

        return $conversation;
    }
}
