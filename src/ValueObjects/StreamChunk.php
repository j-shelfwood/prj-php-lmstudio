<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObjects;

use Shelfwood\LMStudio\Enums\FinishReason;
use Shelfwood\LMStudio\Enums\ToolType;

/**
 * Represents a chunk from a streaming response.
 */
class StreamChunk implements \JsonSerializable
{
    private array $rawChunk;

    private ?string $content = null;

    private array $toolCalls = [];

    private ?FinishReason $finishReason = null;

    private ?string $error = null;

    /**
     * Create a new stream chunk.
     */
    public function __construct(array $rawChunk)
    {
        $this->rawChunk = $rawChunk;
        $this->parseChunk();
    }

    /**
     * Parse the raw chunk data.
     */
    private function parseChunk(): void
    {
        // Extract content
        if (isset($this->rawChunk['choices'][0]['delta']['content'])) {
            $this->content = $this->rawChunk['choices'][0]['delta']['content'];
        }

        // Extract tool calls
        if (isset($this->rawChunk['choices'][0]['delta']['tool_calls'])) {
            foreach ($this->rawChunk['choices'][0]['delta']['tool_calls'] as $toolCallData) {
                $id = $toolCallData['id'] ?? null;

                if ($id) {
                    $this->toolCalls[] = new ToolCall(
                        id: $id,
                        type: isset($toolCallData['type'])
                            ? ToolType::from($toolCallData['type'])
                            : ToolType::FUNCTION,
                        function: new FunctionCall(
                            name: $toolCallData['function']['name'] ?? '',
                            arguments: $toolCallData['function']['arguments'] ?? '{}'
                        )
                    );
                }
            }
        }

        // Extract finish reason
        if (isset($this->rawChunk['choices'][0]['finish_reason']) && $this->rawChunk['choices'][0]['finish_reason'] !== null) {
            $this->finishReason = FinishReason::tryFrom($this->rawChunk['choices'][0]['finish_reason']);
        }

        // Extract error
        if (isset($this->rawChunk['error'])) {
            $this->error = $this->rawChunk['error']['message'] ?? 'Unknown error';
        }
    }

    /**
     * Check if the chunk has content.
     */
    public function hasContent(): bool
    {
        return $this->content !== null && $this->content !== '';
    }

    /**
     * Get the content of the chunk.
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Check if the chunk has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    /**
     * Get the tool calls in the chunk.
     *
     * @return array<ToolCall>
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * Check if the chunk is complete.
     */
    public function isComplete(): bool
    {
        return $this->finishReason !== null;
    }

    /**
     * Get the finish reason of the chunk.
     */
    public function getFinishReason(): ?FinishReason
    {
        return $this->finishReason;
    }

    /**
     * Check if the chunk has an error.
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Get the error message of the chunk.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get the raw chunk data.
     */
    public function getRawChunk(): array
    {
        return $this->rawChunk;
    }

    /**
     * Convert the chunk to an array.
     */
    public function jsonSerialize(): array
    {
        return $this->rawChunk;
    }
}
