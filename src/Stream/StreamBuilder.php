<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Stream;

use Shelfwood\LMStudio\Api\Contract\LMStudioClientInterface;
use Shelfwood\LMStudio\Http\Factory\RequestFactoryInterface;
use Shelfwood\LMStudio\Tool\ToolRegistry;
use Shelfwood\LMStudio\ValueObject\ChatHistory;

/**
 * Builder for creating stream responses.
 */
class StreamBuilder implements StreamBuilderInterface
{
    protected ?ChatHistory $chatHistory = null;

    protected ?string $model = null;

    protected array $tools = [];

    protected ?ToolRegistry $toolRegistry = null;

    protected string $toolUseMode = 'auto';

    protected float $temperature = 0.7;

    protected ?int $maxTokens = null;

    protected bool $debug = false;

    protected $contentCallback = null;

    protected $toolCallCallback = null;

    protected $toolResultCallback = null;

    protected $completeCallback = null;

    protected $errorCallback = null;

    protected $stateChangeCallback = null;

    /**
     * Create a new stream builder.
     */
    public function __construct(
        protected LMStudioClientInterface $client,
        protected RequestFactoryInterface $requestFactory
    ) {}

    /**
     * Create a new stream builder with a fluent interface.
     */
    public static function create(
        LMStudioClientInterface $client,
        RequestFactoryInterface $requestFactory
    ): self {
        return new self($client, $requestFactory);
    }

    /**
     * Set the chat history.
     */
    public function withChatHistory(ChatHistory $chatHistory): self
    {
        $this->chatHistory = $chatHistory;

        return $this;
    }

    /**
     * Set the chat history (alias for backward compatibility).
     */
    public function withHistory(ChatHistory $chatHistory): self
    {
        return $this->withChatHistory($chatHistory);
    }

    /**
     * Set the model.
     */
    public function withModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set the tools.
     */
    public function withTools(array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    /**
     * Set the tool registry.
     */
    public function withToolRegistry(ToolRegistry $toolRegistry): self
    {
        $this->toolRegistry = $toolRegistry;

        return $this;
    }

    /**
     * Set the tool use mode.
     */
    public function withToolUseMode(string $mode): self
    {
        $this->toolUseMode = $mode;

        return $this;
    }

    /**
     * Set the temperature.
     */
    public function withTemperature(float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    /**
     * Set the max tokens.
     */
    public function withMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    /**
     * Enable debug mode.
     */
    public function withDebug(bool $debug = true): self
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * Set the content callback.
     */
    public function onContent(callable $callback): self
    {
        $this->contentCallback = $callback;

        return $this;
    }

    /**
     * Set the content callback (alias for backward compatibility).
     */
    public function stream(?callable $callback = null): self
    {
        if ($callback !== null) {
            $this->contentCallback = $callback;
        }

        return $this;
    }

    /**
     * Set the tool call callback.
     */
    public function onToolCall(callable $callback): self
    {
        $this->toolCallCallback = $callback;

        return $this;
    }

    /**
     * Set the tool result callback.
     */
    public function onToolResult(callable $callback): self
    {
        $this->toolResultCallback = $callback;

        return $this;
    }

    /**
     * Set the complete callback.
     */
    public function onComplete(callable $callback): self
    {
        $this->completeCallback = $callback;

        return $this;
    }

    /**
     * Set the error callback.
     */
    public function onError(callable $callback): self
    {
        $this->errorCallback = $callback;

        return $this;
    }

    /**
     * Set the state change callback.
     */
    public function onStateChange(callable $callback): self
    {
        $this->stateChangeCallback = $callback;

        return $this;
    }

    /**
     * Build the stream response.
     */
    public function build(): StreamResponseInterface
    {
        if ($this->chatHistory === null) {
            throw new \InvalidArgumentException('Chat history is required');
        }

        if ($this->client === null) {
            throw new \InvalidArgumentException('Client is required to build a StreamResponse');
        }

        return new StreamResponse(
            client: $this->client,
            requestFactory: $this->requestFactory,
            chatHistory: $this->chatHistory,
            model: $this->model,
            tools: $this->tools,
            toolRegistry: $this->toolRegistry,
            toolUseMode: $this->toolUseMode,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            debug: $this->debug,
            contentCallback: $this->contentCallback,
            toolCallCallback: $this->toolCallCallback,
            toolResultCallback: $this->toolResultCallback,
            completeCallback: $this->completeCallback,
            errorCallback: $this->errorCallback,
            stateChangeCallback: $this->stateChangeCallback
        );
    }

    /**
     * Execute the streaming request.
     */
    public function execute(): void
    {
        if ($this->client === null) {
            throw new \InvalidArgumentException('Client is required for execute()');
        }

        if ($this->contentCallback === null) {
            throw new \InvalidArgumentException('Content callback must be set using stream() method');
        }

        $response = $this->build();
        $response->process(function ($chunk): void {
            // This is a pass-through callback that will be handled by the StreamResponse
        });
    }
}
