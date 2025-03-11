<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Builders;

use Generator;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Tool;

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

        // Create a request object
        $apiVersion = $this->client->getApiVersionNamespace();
        $requestClass = "\\Shelfwood\\LMStudio\\Http\\Requests\\{$apiVersion}\\ChatCompletionRequest";

        // Create the request with required parameters
        $request = new $requestClass(
            $this->history->jsonSerialize(),
            $this->model ?? $this->client->getConfig()->getDefaultModel() ?? 'gpt-3.5-turbo'
        );

        // Set additional parameters
        $request = $request->withTemperature($this->temperature);
        $request = $request->withMaxTokens($this->maxTokens);
        $request = $request->withStreaming(true);

        // Add tools if available
        if (! empty($this->tools)) {
            // Convert Tool objects to arrays
            $toolsArray = array_values(array_map(function ($tool) {
                return $tool instanceof Tool ? $tool->jsonSerialize() : $tool;
            }, $this->tools));

            $request = $request->withTools($toolsArray);
            $request = $request->withToolChoice($this->toolUseMode);

            // Log the tools being used
            if ($this->debug) {
                $logger = $this->client->getConfig()->getLogger();

                if ($logger) {
                    $logger->log('StreamBuilder using tools', [
                        'tools' => $toolsArray,
                        'tool_use_mode' => $this->toolUseMode,
                    ]);
                }
            }
        }

        try {
            $stream = $this->client->streamChatCompletion($request);
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
     * Execute the streaming request with a pre-configured request object.
     *
     * @param  object  $request  The request object to use for streaming
     */
    public function executeWithRequest(object $request): void
    {
        if ($this->contentCallback === null) {
            throw new \InvalidArgumentException('Content callback must be set using stream() method');
        }

        // Ensure the request is set to stream
        if (method_exists($request, 'setStream')) {
            $request->setStream(true);
        }

        try {
            $stream = $this->client->streamChatCompletion($request);
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
     *
     * @param  Generator<\Shelfwood\LMStudio\ValueObjects\StreamChunk>  $stream
     */
    private function processStream(Generator $stream): void
    {
        $content = '';
        $toolCalls = [];

        // Try to get the logger, but don't fail if it's not available
        try {
            $logger = $this->client->getConfig()->getLogger();
        } catch (\Throwable $e) {
            $logger = null;
        }

        foreach ($stream as $chunk) {
            try {
                // Log the chunk if it has tool calls
                if ($chunk->hasToolCalls() && $logger) {
                    $logger->log('Stream chunk contains tool calls', [
                        'tool_calls' => $chunk->getToolCalls(),
                    ]);
                }

                // Process content
                if ($chunk->hasContent()) {
                    $content .= $chunk->getContent();
                    ($this->contentCallback)($chunk);
                }

                // Process tool calls
                if ($chunk->hasToolCalls() && $this->toolCallCallback !== null) {
                    $newToolCalls = $chunk->getToolCalls();
                    $toolCalls = array_merge($toolCalls, $newToolCalls);
                    ($this->toolCallCallback)($newToolCalls);
                }

                // Process completion
                if ($chunk->isComplete() && $this->completeCallback !== null) {
                    ($this->completeCallback)($content, $toolCalls);
                }
            } catch (\Exception $e) {
                if ($logger) {
                    $logger->log('Error processing stream chunk', [
                        'error' => $e->getMessage(),
                        'chunk' => $chunk->getRawChunk(),
                    ]);
                }

                if ($this->errorCallback !== null) {
                    ($this->errorCallback)($e);
                }
            }
        }
    }
}
