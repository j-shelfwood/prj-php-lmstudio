<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObject;

use Shelfwood\LMStudio\Enum\FinishReason;
use Shelfwood\LMStudio\Enum\ToolType;
use Shelfwood\LMStudio\Stream\StreamState;
use Shelfwood\LMStudio\Tool\ToolResponse;
use Shelfwood\LMStudio\ValueObject\ToolCall;

/**
 * Represents a chunk from a streaming response.
 */
class StreamChunk implements \JsonSerializable
{
    private array $rawChunk = [];
    private ?string $content = null;
    private array $toolCalls = [];
    private ?FinishReason $finishReason = null;
    private ?string $error = null;
    private ?StreamState $state = null;

    /**
     * Create a new stream chunk.
     */
    public function __construct(
        mixed $contentOrRawChunk = null,
        array $toolCalls = [],
        ?string $finishReason = null,
        ?string $error = null,
        ?StreamState $state = null
    ) {
        // Handle the case where the first parameter is an array (raw chunk)
        if (is_array($contentOrRawChunk)) {
            $this->rawChunk = $contentOrRawChunk;
            $this->parseChunk();
        } else {
            $this->content = $contentOrRawChunk;
            $this->toolCalls = $toolCalls;
            $this->finishReason = $finishReason ? FinishReason::tryFrom($finishReason) : null;
            $this->error = $error;
            $this->state = $state;
        }
    }

    /**
     * Create an error chunk.
     */
    public static function error(string $message): self
    {
        return new self(
            error: $message,
            finishReason: 'error',
            state: StreamState::ERROR
        );
    }

    /**
     * Create a tool result chunk.
     */
    public static function toolResult(ToolResponse $toolResponse): self
    {
        return new self(
            toolCalls: [$toolResponse],
            state: StreamState::PROCESSING_TOOL_CALLS
        );
    }

    /**
     * Create a completion chunk.
     */
    public static function completion(string $finishReason = 'stop'): self
    {
        return new self(
            finishReason: $finishReason,
            state: StreamState::COMPLETED
        );
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
            $toolCalls = [];
            foreach ($this->rawChunk['choices'][0]['delta']['tool_calls'] as $toolCallData) {
                $id = $toolCallData['id'] ?? null;

                if ($id) {
                    $toolCalls[] = new ToolCall(
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
            $this->toolCalls = $toolCalls;
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
        $data = [
            'content' => $this->content,
            'tool_calls' => [],
            'finish_reason' => $this->finishReason?->value,
            'error' => $this->error,
            'state' => $this->state?->value,
        ];

        // Format tool calls based on their type
        foreach ($this->toolCalls as $toolCall) {
            if ($toolCall instanceof ToolCall) {
                $data['tool_calls'][] = [
                    'id' => $toolCall->id,
                    'type' => $toolCall->type->value,
                    'function' => [
                        'name' => $toolCall->function->name,
                        'arguments' => $toolCall->function->arguments,
                    ],
                ];
            } elseif ($toolCall instanceof ToolResponse) {
                $data['tool_results'][] = $toolCall->jsonSerialize();
            }
        }

        // Remove null values
        return array_filter($data, fn ($value) => $value !== null && (!is_array($value) || !empty($value)));
    }
}
