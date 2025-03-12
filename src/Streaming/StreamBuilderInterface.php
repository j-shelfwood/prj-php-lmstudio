<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Streaming;

use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;

/**
 * Interface for stream builders.
 */
interface StreamBuilderInterface
{
    /**
     * Set the chat history.
     */
    public function withChatHistory(ChatHistory $chatHistory): self;

    /**
     * Set the chat history (alias for backward compatibility).
     */
    public function withHistory(ChatHistory $chatHistory): self;

    /**
     * Set the model.
     */
    public function withModel(string $model): self;

    /**
     * Set the tools.
     */
    public function withTools(array $tools): self;

    /**
     * Set the tool registry.
     */
    public function withToolRegistry(ToolRegistry $toolRegistry): self;

    /**
     * Set the tool use mode.
     */
    public function withToolUseMode(string $mode): self;

    /**
     * Set the temperature.
     */
    public function withTemperature(float $temperature): self;

    /**
     * Set the max tokens.
     */
    public function withMaxTokens(int $maxTokens): self;

    /**
     * Enable debug mode.
     */
    public function withDebug(bool $debug = true): self;

    /**
     * Set the content callback.
     */
    public function onContent(callable $callback): self;

    /**
     * Set the content callback (alias for backward compatibility).
     */
    public function stream(?callable $callback = null): self;

    /**
     * Set the tool call callback.
     */
    public function onToolCall(callable $callback): self;

    /**
     * Set the tool result callback.
     */
    public function onToolResult(callable $callback): self;

    /**
     * Set the complete callback.
     */
    public function onComplete(callable $callback): self;

    /**
     * Set the error callback.
     */
    public function onError(callable $callback): self;

    /**
     * Set the state change callback.
     */
    public function onStateChange(callable $callback): self;

    /**
     * Build the stream response.
     */
    public function build(): StreamResponseInterface;

    /**
     * Execute the streaming request.
     */
    public function execute(): void;
}
