<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Conversations;

use Shelfwood\LMStudio\Builders\StreamBuilder;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Enums\ToolChoice;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\Utilities\ToolCallExtractor;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;
use Shelfwood\LMStudio\ValueObjects\ToolCall;

/**
 * Represents a conversation with an AI assistant.
 */
class Conversation
{
    private ChatHistory $history;

    private string $id;

    private string $title;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $updatedAt = null;

    private array $metadata = [];

    private LMStudioClientInterface $client;

    private string $model;

    private float $temperature = 0.7;

    private int $maxTokens = 4000;

    private ?ToolRegistry $toolRegistry = null;

    private ConversationSerializer $serializer;

    /**
     * Create a new conversation.
     */
    public function __construct(
        LMStudioClientInterface $client,
        string $title = 'New Conversation',
        ?string $id = null,
        ?ChatHistory $history = null
    ) {
        $this->client = $client;
        $this->title = $title;
        $this->id = $id ?? uniqid('conv_');
        $this->history = $history ?? new ChatHistory;
        $this->createdAt = new \DateTimeImmutable;
        $this->model = $client->getConfig()->getDefaultModel() ?? 'qwen2.5-7b-instruct-1m';

        // Initialize serializer
        $this->serializer = new ConversationSerializer;
    }

    /**
     * Create a new conversation with a system message.
     *
     * @param  LMStudioClientInterface  $client  The client to use for API calls
     * @param  string  $systemMessage  The system message to set
     * @param  string  $title  The title of the conversation
     * @return self A new conversation instance
     */
    public static function withSystemMessage(
        LMStudioClientInterface $client,
        string $systemMessage,
        string $title = 'New Conversation'
    ): self {
        $conversation = new self($client, $title);
        $conversation->addSystemMessage($systemMessage);

        return $conversation;
    }

    /**
     * Create a new conversation with tools enabled.
     *
     * @param  LMStudioClientInterface  $client  The client to use for API calls
     * @param  ToolRegistry  $toolRegistry  The tool registry to use
     * @param  string  $systemMessage  The system message to set
     * @param  string  $title  The title of the conversation
     * @return self A new conversation instance
     */
    public static function withTools(
        LMStudioClientInterface $client,
        ToolRegistry $toolRegistry,
        string $systemMessage = 'You are a helpful assistant.',
        string $title = 'New Conversation'
    ): self {
        $conversation = new self($client, $title);
        $conversation->addSystemMessage($systemMessage);
        $conversation->setToolRegistry($toolRegistry);

        return $conversation;
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
     * Get the chat history.
     */
    public function getHistory(): ChatHistory
    {
        return $this->history;
    }

    /**
     * Get all messages in the conversation.
     *
     * @return array<Message>
     */
    public function getMessages(): array
    {
        return $this->history->getMessages();
    }

    /**
     * Add a system message to the conversation.
     */
    public function addSystemMessage(string $content): self
    {
        $this->history->addSystemMessage($content);
        $this->updatedAt = new \DateTimeImmutable;

        return $this;
    }

    /**
     * Add a user message to the conversation.
     */
    public function addUserMessage(string $content, ?string $name = null): self
    {
        $this->history->addUserMessage($content, $name);
        $this->updatedAt = new \DateTimeImmutable;

        return $this;
    }

    /**
     * Add an assistant message to the conversation.
     */
    public function addAssistantMessage(string $content, ?array $toolCalls = null): self
    {
        $this->history->addAssistantMessage($content, $toolCalls);
        $this->updatedAt = new \DateTimeImmutable;

        return $this;
    }

    /**
     * Add a tool message to the conversation.
     */
    public function addToolMessage(string $content, string $toolCallId): self
    {
        $this->history->addToolMessage($content, $toolCallId);
        $this->updatedAt = new \DateTimeImmutable;

        return $this;
    }

    /**
     * Set the tool registry for the conversation.
     */
    public function setToolRegistry(ToolRegistry $toolRegistry): self
    {
        $this->toolRegistry = $toolRegistry;
        $this->updatedAt = new \DateTimeImmutable;

        return $this;
    }

    /**
     * Get the tool registry for the conversation.
     */
    public function getToolRegistry(): ?ToolRegistry
    {
        return $this->toolRegistry;
    }

    /**
     * Check if tools are enabled.
     */
    public function hasTools(): bool
    {
        return $this->toolRegistry !== null && $this->toolRegistry->count() > 0;
    }

    /**
     * Execute a tool call.
     */
    public function executeToolCall(ToolCall $toolCall): ?string
    {
        if (! $this->hasTools() || ! $this->toolRegistry->has($toolCall->function->name)) {
            return null;
        }

        try {
            return $this->toolRegistry->execute($toolCall);
        } catch (\Exception $e) {
            // Log the error but continue
            error_log("Error executing tool '{$toolCall->function->name}': {$e->getMessage()}");

            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Set the model to use for the conversation.
     */
    public function setModel(string $model): self
    {
        $this->model = $model;
        $this->updatedAt = new \DateTimeImmutable;

        return $this;
    }

    /**
     * Get the model used for the conversation.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Set the temperature for the conversation.
     */
    public function setTemperature(float $temperature): self
    {
        $this->temperature = $temperature;
        $this->updatedAt = new \DateTimeImmutable;

        return $this;
    }

    /**
     * Get the temperature for the conversation.
     */
    public function getTemperature(): float
    {
        return $this->temperature;
    }

    /**
     * Set the maximum number of tokens for the conversation.
     */
    public function setMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;
        $this->updatedAt = new \DateTimeImmutable;

        return $this;
    }

    /**
     * Get the maximum number of tokens for the conversation.
     */
    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    /**
     * Send a message and get a response.
     *
     * This is a convenience method that adds a user message and gets a response in one call.
     *
     * @param  string  $message  The message to send
     * @param  bool  $stream  Whether to stream the response
     * @param  callable|null  $streamCallback  Callback for streaming chunks (only used if $stream is true)
     * @return string The response from the assistant
     */
    public function send(string $message, bool $stream = false, ?callable $streamCallback = null): string
    {
        // Add the user message
        $this->addUserMessage($message);

        // Get the response
        if ($stream && $streamCallback !== null) {
            $content = '';

            // Stream the response with the provided callback
            $this->streamResponse(function ($chunk) use ($streamCallback, &$content): void {
                if ($chunk->hasContent()) {
                    $content .= $chunk->getContent();
                    $streamCallback($chunk);
                }
            });

            return $content;
        }

        // Get a non-streaming response
        return $this->getResponse();
    }

    /**
     * Send a message and get a streaming response with a callback.
     *
     * @param  string  $message  The message to send
     * @param  callable  $callback  Callback for streaming chunks
     * @return string The complete response text
     */
    public function sendStreaming(string $message, callable $callback): string
    {
        return $this->send($message, true, $callback);
    }

    /**
     * Get a response from the AI assistant.
     */
    public function getResponse(): string
    {
        // Create a request object
        $request = $this->createRequest();

        $response = $this->client->chatCompletion($request);

        // Extract content from the response
        $content = $response->choices[0]->message->content ?? '';

        // Add the assistant's response to the history
        $this->addAssistantMessage($content);

        // Process tool calls if present
        $toolCalls = ToolCallExtractor::extract($response);

        // Only process tool calls if we have tools registered
        if ($this->hasTools() && ! empty($toolCalls)) {
            $processedToolCalls = $this->processToolCalls($toolCalls);

            // If we processed tool calls, get a follow-up response
            if (! empty($processedToolCalls)) {
                return $this->getResponse();
            }
        }

        return $content;
    }

    /**
     * Process tool calls and add results to conversation history.
     *
     * @param  array  $toolCalls  The tool calls to process
     * @return array The processed tool calls
     */
    private function processToolCalls(array $toolCalls): array
    {
        $processedToolCalls = [];

        foreach ($toolCalls as $toolCall) {
            $result = $this->executeToolCall($toolCall);

            if ($result !== null) {
                $this->addToolMessage($result, $toolCall->id);
                $processedToolCalls[] = $toolCall;
            }
        }

        return $processedToolCalls;
    }

    /**
     * Stream a response with a callback for each chunk.
     */
    public function streamResponse(callable $callback): void
    {
        // Get the request object
        $request = $this->createRequest();
        $request->setStream(true);

        // Create a stream builder
        $streamBuilder = new StreamBuilder($this->client);

        // Content callback
        $streamBuilder->stream(function ($chunk) use ($callback): void {
            if ($chunk->hasContent()) {
                $callback($chunk->getContent());
            }
        });

        // Tool call callback
        if ($this->hasTools()) {
            $streamBuilder->onToolCall(function ($toolCall) {
                $result = $this->executeToolCall($toolCall);

                if ($result !== null) {
                    // Add the tool result to the conversation
                    $this->addToolMessage($result, $toolCall->id);

                    return $result;
                }

                return 'Error: Tool execution failed';
            });
        }

        // Execute the stream with the request
        $streamBuilder->executeWithRequest($request);
    }

    /**
     * Create a request object for the conversation.
     */
    private function createRequest(): mixed
    {
        // Create a request object
        $apiVersion = $this->client->getApiVersionNamespace();
        $requestClass = "\\Shelfwood\\LMStudio\\Http\\Requests\\{$apiVersion}\\ChatCompletionRequest";

        // Create the request with required parameters
        $request = new $requestClass(
            $this->history->jsonSerialize(),
            $this->model
        );

        // Set additional parameters
        $request = $request->withTemperature($this->temperature);
        $request = $request->withMaxTokens($this->maxTokens);

        // Add tools if available
        if ($this->hasTools()) {
            $request = $request->withTools($this->getToolsForRequest());
            $request = $request->withToolChoice(ToolChoice::AUTO->value);
        }

        return $request;
    }

    /**
     * Convert the conversation to JSON.
     */
    public function toJson(): string
    {
        return $this->serializer->toJson($this);
    }

    /**
     * Create a conversation from JSON.
     */
    public static function fromJson(string $json, LMStudioClientInterface $client): self
    {
        $serializer = new ConversationSerializer;

        return $serializer->fromJson($json, $client);
    }

    /**
     * Set metadata for the conversation.
     */
    public function setMetadata(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->metadata[$k] = $v;
            }
        } else {
            $this->metadata[$key] = $value;
        }

        $this->updatedAt = new \DateTimeImmutable;

        return $this;
    }

    /**
     * Get metadata from the conversation.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get all metadata from the conversation.
     */
    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the client used for the conversation.
     */
    public function getClient(): LMStudioClientInterface
    {
        return $this->client;
    }

    /**
     * Create a new conversation builder.
     */
    public static function builder(LMStudioClientInterface $client): ConversationBuilder
    {
        return new ConversationBuilder($client);
    }

    /**
     * Get the tools as an array for API requests.
     */
    private function getToolsForRequest(): array
    {
        if (! $this->hasTools()) {
            return [];
        }

        return array_map(
            fn ($tool) => $tool->jsonSerialize(),
            $this->toolRegistry->getTools()
        );
    }
}
