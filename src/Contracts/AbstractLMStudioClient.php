<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Contracts;

use Generator;
use GuzzleHttp\Psr7\Utils;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Exceptions\StreamingException;
use Shelfwood\LMStudio\Http\Client;
use Shelfwood\LMStudio\Http\Requests\Common\RequestInterface;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;

/**
 * Abstract base client for LMStudio API clients.
 */
abstract class AbstractLMStudioClient implements ConfigAwareInterface, LMStudioClientInterface
{
    protected Client $client;

    protected ?StreamingResponseHandler $streamingHandler = null;

    protected string $apiVersion;

    protected LMStudioConfig $config;

    /**
     * Create a new client instance.
     */
    public function __construct(LMStudioConfig $config)
    {
        $this->config = $config;
        $this->client = new Client($config);
        $this->streamingHandler = null;
    }

    /**
     * Set the HTTP client instance.
     */
    public function setHttpClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Set the streaming response handler instance.
     */
    public function setStreamingHandler(StreamingResponseHandler $handler): self
    {
        $this->streamingHandler = $handler;

        return $this;
    }

    /**
     * Get the client configuration.
     */
    public function getConfig(): LMStudioConfig
    {
        return $this->config;
    }

    /**
     * Get the list of available models.
     */
    public function models(): array
    {
        return $this->client->get($this->apiVersion.'/models');
    }

    /**
     * Retrieve information about a specific model.
     */
    public function model(string $modelId): array
    {
        return $this->client->get($this->apiVersion.'/models/'.$modelId);
    }

    /**
     * Create a chat completion using a request object.
     */
    abstract public function chatCompletion(RequestInterface $request): mixed;

    /**
     * Create a streaming chat completion using a request object.
     */
    abstract public function streamChatCompletion(RequestInterface $request): Generator;

    /**
     * Create a text completion using a request object.
     */
    abstract public function textCompletion(RequestInterface $request): mixed;

    /**
     * Create a streaming text completion using a request object.
     */
    abstract public function streamTextCompletion(RequestInterface $request): Generator;

    /**
     * Create embeddings using a request object.
     */
    abstract public function createEmbeddings(RequestInterface $request): mixed;

    /**
     * Create a chat completion (legacy method).
     */
    abstract public function chat(array $messages, array $options = []): mixed;

    /**
     * Create a streaming chat completion (legacy method).
     */
    public function streamChat(array $messages, array $options = []): Generator
    {
        // Perform health check if enabled
        if ($this->config->isHealthCheckEnabled()) {
            if (! $this->client->checkHealth()) {
                throw new StreamingException('LMStudio server is not available');
            }
        }

        return $this->client->stream($this->apiVersion.'/chat/completions', array_merge([
            'messages' => $messages,
            'stream' => true,
        ], $options));
    }

    /**
     * Create a completion (legacy method).
     */
    abstract public function completion(string $prompt, array $options = []): mixed;

    /**
     * Create a streaming completion (legacy method).
     */
    public function streamCompletion(string $prompt, array $options = []): Generator
    {
        // Perform health check if enabled
        if ($this->config->isHealthCheckEnabled()) {
            if (! $this->client->checkHealth()) {
                throw new StreamingException('LMStudio server is not available');
            }
        }

        return $this->client->stream($this->apiVersion.'/completions', array_merge([
            'prompt' => $prompt,
            'stream' => true,
        ], $options));
    }

    /**
     * Create embeddings (legacy method).
     */
    abstract public function embeddings(string|array $input, array $options = []): mixed;

    /**
     * Accumulate content from a streaming chat completion.
     */
    public function accumulateChatContent(array|ChatHistory $messages, array $options = []): string
    {
        if ($messages instanceof ChatHistory) {
            $messages = $messages->getMessages();
        }

        return $this->accumulateContent($this->streamChat($messages, $options));
    }

    /**
     * Accumulate tool calls from a streaming chat completion.
     */
    public function accumulateChatToolCalls(array|ChatHistory $messages, array $options = []): array
    {
        if ($messages instanceof ChatHistory) {
            $messages = $messages->getMessages();
        }

        return $this->accumulateToolCalls($this->streamChat($messages, $options));
    }

    /**
     * Accumulate content from a streaming text completion.
     */
    public function accumulateCompletionContent(string $prompt, array $options = []): string
    {
        return $this->accumulateContent($this->streamCompletion($prompt, $options));
    }

    /**
     * Accumulate content from a streaming response.
     */
    protected function accumulateContent(Generator $stream): string
    {
        try {
            $handler = $this->getStreamingHandler();

            return $handler->accumulateContent($stream);
        } catch (\Exception $e) {
            if ($e instanceof StreamingException) {
                throw $e;
            }

            throw new StreamingException(
                "Failed to accumulate content from streaming response: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Accumulate tool calls from a streaming response.
     */
    protected function accumulateToolCalls(Generator $stream): array
    {
        try {
            $handler = $this->getStreamingHandler();

            return $handler->accumulateToolCalls($stream);
        } catch (\Exception $e) {
            if ($e instanceof StreamingException) {
                throw $e;
            }

            throw new StreamingException(
                "Failed to accumulate tool calls from streaming response: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Get the streaming handler.
     *
     * @throws \RuntimeException if the streaming handler is not set
     */
    protected function getStreamingHandler(): StreamingResponseHandler
    {
        if ($this->streamingHandler === null) {
            // Create an empty stream for initialization
            $stream = Utils::streamFor('');
            $this->streamingHandler = new StreamingResponseHandler($stream);
        }

        return $this->streamingHandler;
    }
}
