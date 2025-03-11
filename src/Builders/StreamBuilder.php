<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Builders;

use Generator;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\StreamChunk;
use Shelfwood\LMStudio\ValueObjects\Tool;
use Shelfwood\LMStudio\ValueObjects\ToolCall;

/**
 * Builder for streaming chat completions.
 */
class StreamBuilder
{
    private ChatHistory $history;

    private ?string $model = null;

    private array $tools = [];

    private string $toolUseMode = 'auto';

    private float $temperature = 0.7;

    private int $maxTokens = 4000;

    private bool $debug = false;

    private $contentCallback = null;

    private $toolCallCallback = null;

    private $completeCallback = null;

    private $errorCallback = null;

    private LMStudioClientInterface $client;

    private ?ToolRegistry $toolRegistry = null;

    /**
     * Create a new stream builder.
     */
    public function __construct(LMStudioClientInterface $client)
    {
        $this->client = $client;
        $this->history = new ChatHistory;
    }

    /**
     * Set the chat history.
     */
    public function withHistory(ChatHistory $history): self
    {
        $this->history = $history;

        return $this;
    }

    /**
     * Set the model to use.
     */
    public function withModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set the tools to use.
     *
     * @param  array<Tool>  $tools
     */
    public function withTools(array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    /**
     * Set the tool registry to use.
     */
    public function withToolRegistry(ToolRegistry $toolRegistry): self
    {
        $this->toolRegistry = $toolRegistry;
        $this->tools = $toolRegistry->getTools();

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
     * Set the maximum number of tokens.
     */
    public function withMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    /**
     * Enable or disable debug mode.
     */
    public function withDebug(bool $debug): self
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * Set the callback for content chunks.
     */
    public function stream(?callable $callback = null): self
    {
        $this->contentCallback = $callback;

        return $this;
    }

    /**
     * Set the callback for tool calls.
     */
    public function onToolCall(callable $callback): self
    {
        $this->toolCallCallback = $callback;

        return $this;
    }

    /**
     * Set the callback for completion.
     */
    public function onComplete(callable $callback): self
    {
        $this->completeCallback = $callback;

        return $this;
    }

    /**
     * Set the callback for errors.
     */
    public function onError(callable $callback): self
    {
        $this->errorCallback = $callback;

        return $this;
    }

    /**
     * Execute the streaming request.
     */
    public function execute(): void
    {
        if ($this->contentCallback === null) {
            throw new \InvalidArgumentException('Content callback must be set using stream() method');
        }

        $options = [
            'model' => $this->model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'stream' => true,
        ];

        // Add tools if available
        if (! empty($this->tools)) {
            // Convert Tool objects to arrays and ensure it's an array, not an object
            $toolsArray = array_values(array_map(function ($tool) {
                return $tool instanceof Tool ? $tool->jsonSerialize() : $tool;
            }, $this->tools));

            $options['tools'] = $toolsArray;
            $options['tool_choice'] = $this->toolUseMode;

            // Log the tools being used
            $logger = $this->client->getConfig()->getLogger();
            $logger->log('StreamBuilder executing with tools', [
                'tool_count' => count($this->tools),
                'tool_names' => array_map(function ($tool) {
                    if ($tool instanceof Tool) {
                        return $tool->jsonSerialize()['function']['name'] ?? 'unknown';
                    }

                    return $tool['function']['name'] ?? 'unknown';
                }, $this->tools),
                'tool_use_mode' => $this->toolUseMode,
            ]);
        }

        try {
            $stream = $this->client->streamChat($this->history->jsonSerialize(), $options);
            $this->processStream($stream);
        } catch (\Exception $e) {
            if ($this->errorCallback !== null) {
                ($this->errorCallback)($e);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Process the streaming response.
     */
    private function processStream(Generator $stream): void
    {
        $content = '';
        $toolCalls = [];
        $currentToolCall = null;
        $logger = $this->client->getConfig()->getLogger();

        foreach ($stream as $rawChunk) {
            try {
                $chunk = new StreamChunk($rawChunk);

                // Log the chunk if it has tool calls
                if ($chunk->hasToolCalls()) {
                    $logger->log('StreamBuilder received tool call chunk', [
                        'tool_calls' => $chunk->getToolCalls(),
                    ]);
                }

                // Handle content
                if ($chunk->hasContent()) {
                    $newContent = $chunk->getContent();
                    $content .= $newContent;
                    ($this->contentCallback)($chunk);
                }

                // Handle tool calls
                if ($chunk->hasToolCalls() && $this->toolCallCallback !== null) {
                    foreach ($chunk->getToolCalls() as $toolCallData) {
                        // Check if this is a new tool call or continuation
                        $id = $toolCallData['id'] ?? null;

                        if ($id && ! isset($toolCalls[$id])) {
                            // New tool call
                            $toolCalls[$id] = $toolCallData;

                            // Create a ToolCall object
                            $toolCall = new ToolCall(
                                $id,
                                $toolCallData['type'] ?? 'function',
                                new \Shelfwood\LMStudio\ValueObjects\FunctionCall(
                                    $toolCallData['function']['name'] ?? '',
                                    $toolCallData['function']['arguments'] ?? '{}'
                                )
                            );

                            // Log the tool call
                            $logger->log('StreamBuilder executing tool call', [
                                'tool_id' => $id,
                                'tool_name' => $toolCallData['function']['name'] ?? 'unknown',
                                'arguments' => $toolCallData['function']['arguments'] ?? '{}',
                            ]);

                            // Execute the tool call
                            $result = ($this->toolCallCallback)($toolCall);

                            // Log the tool call result
                            $logger->log('StreamBuilder tool call result', [
                                'tool_id' => $id,
                                'result' => $result,
                            ]);
                        }
                    }
                }

                // Handle completion
                if ($chunk->isComplete() && $this->completeCallback !== null) {
                    $logger->log('StreamBuilder stream completed', [
                        'content_length' => strlen($content),
                        'tool_call_count' => count($toolCalls),
                    ]);

                    ($this->completeCallback)($content, array_values($toolCalls));
                }
            } catch (\Exception $e) {
                $logger->logError('StreamBuilder chunk processing error', $e, [
                    'raw_chunk' => $rawChunk,
                ]);

                if ($this->errorCallback !== null) {
                    ($this->errorCallback)($e);
                } else {
                    throw $e;
                }
            }
        }

        // If we didn't get a completion signal but we're done with the stream,
        // call the completion callback anyway
        if (! empty($content) && $this->completeCallback !== null) {
            $logger->log('StreamBuilder stream ended without completion signal', [
                'content_length' => strlen($content),
                'tool_call_count' => count($toolCalls),
            ]);

            ($this->completeCallback)($content, array_values($toolCalls));
        }
    }
}
