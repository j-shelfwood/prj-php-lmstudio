<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio;

use Generator;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Http\Client;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;
use Shelfwood\LMStudio\Requests\Common\RequestInterface;
use Shelfwood\LMStudio\Requests\V0\ChatCompletionRequest;
use Shelfwood\LMStudio\Requests\V0\EmbeddingRequest;
use Shelfwood\LMStudio\Requests\V0\TextCompletionRequest;
use Shelfwood\LMStudio\Responses\V0\ChatCompletion;
use Shelfwood\LMStudio\Responses\V0\Embedding;
use Shelfwood\LMStudio\Responses\V0\TextCompletion;
use Shelfwood\LMStudio\Traits\HandlesStreamingResponses;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;

class LMS implements LMStudioClientInterface
{
    use HandlesStreamingResponses;

    protected Client $client;

    protected StreamingResponseHandler $streamingHandler;

    private string $apiVersion = 'api/v0';

    private LMStudioConfig $config;

    public function __construct(LMStudioConfig $config)
    {
        $this->config = $config;
        // Use the base URL as is, but ensure paths include api/v0
        $this->client = new Client($config);
        $this->streamingHandler = new StreamingResponseHandler;
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
    public function chatCompletion(RequestInterface $request): ChatCompletion
    {
        $response = $this->client->post(
            $this->apiVersion.'/chat/completions',
            $request->toArray()
        );

        return ChatCompletion::fromArray($response);
    }

    /**
     * Create a streaming chat completion using a request object.
     */
    public function streamChatCompletion(RequestInterface $request): Generator
    {
        // Ensure streaming is enabled
        $data = $request->toArray();
        $data['stream'] = true;

        return $this->client->stream(
            $this->apiVersion.'/chat/completions',
            $data
        );
    }

    /**
     * Create a text completion using a request object.
     */
    public function textCompletion(RequestInterface $request): TextCompletion
    {
        $response = $this->client->post(
            $this->apiVersion.'/completions',
            $request->toArray()
        );

        return TextCompletion::fromArray($response);
    }

    /**
     * Create a streaming text completion using a request object.
     */
    public function streamTextCompletion(RequestInterface $request): Generator
    {
        // Ensure streaming is enabled
        $data = $request->toArray();
        $data['stream'] = true;

        return $this->client->stream(
            $this->apiVersion.'/completions',
            $data
        );
    }

    /**
     * Create embeddings using a request object.
     */
    public function createEmbeddings(RequestInterface $request): Embedding
    {
        $response = $this->client->post(
            $this->apiVersion.'/embeddings',
            $request->toArray()
        );

        return Embedding::fromArray($response);
    }

    /**
     * Create a chat completion (legacy method).
     *
     * @deprecated Use chatCompletion() instead
     */
    public function chat(array $messages, array $options = []): ChatCompletion
    {
        $model = $options['model'] ?? 'gpt-3.5-turbo';
        unset($options['model']);

        $request = new ChatCompletionRequest($messages, $model, $options);

        return $this->chatCompletion($request);
    }

    /**
     * Create a streaming chat completion (legacy method).
     *
     * @deprecated Use streamChatCompletion() instead
     */
    public function streamChat(array $messages, array $options = []): Generator
    {
        $model = $options['model'] ?? 'gpt-3.5-turbo';
        unset($options['model']);

        $request = new ChatCompletionRequest($messages, $model, $options);
        $request = $request->withStreaming(true);

        return $this->streamChatCompletion($request);
    }

    /**
     * Create a completion (legacy method).
     *
     * @deprecated Use textCompletion() instead
     */
    public function completion(string $prompt, array $options = []): TextCompletion
    {
        $model = $options['model'] ?? 'gpt-3.5-turbo';
        unset($options['model']);

        $request = new TextCompletionRequest($prompt, $model, $options);

        return $this->textCompletion($request);
    }

    /**
     * Create a streaming completion (legacy method).
     *
     * @deprecated Use streamTextCompletion() instead
     */
    public function streamCompletion(string $prompt, array $options = []): Generator
    {
        $model = $options['model'] ?? 'gpt-3.5-turbo';
        unset($options['model']);

        $request = new TextCompletionRequest($prompt, $model, $options);
        $request = $request->withStreaming(true);

        return $this->streamTextCompletion($request);
    }

    /**
     * Create embeddings (legacy method).
     *
     * @deprecated Use createEmbeddings() instead
     */
    public function embeddings(string|array $input, array $options = []): Embedding
    {
        $model = $options['model'] ?? 'text-embedding-ada-002';
        unset($options['model']);

        $request = new EmbeddingRequest($input, $model, $options);

        return $this->createEmbeddings($request);
    }

    /**
     * Accumulate content from a streaming chat completion.
     */
    public function accumulateChatContent(array|ChatHistory $messages, array $options = []): string
    {
        if ($messages instanceof ChatHistory) {
            $messages = $messages->getMessages();
        }

        return $this->streamingHandler->accumulateContent(
            $this->streamChat($messages, $options)
        );
    }

    /**
     * Accumulate tool calls from a streaming chat completion.
     */
    public function accumulateChatToolCalls(array|ChatHistory $messages, array $options = []): array
    {
        if ($messages instanceof ChatHistory) {
            $messages = $messages->getMessages();
        }

        return $this->streamingHandler->accumulateToolCalls(
            $this->streamChat($messages, $options)
        );
    }

    /**
     * Accumulate content from a streaming text completion.
     */
    public function accumulateCompletionContent(string $prompt, array $options = []): string
    {
        return $this->streamingHandler->accumulateContent(
            $this->streamCompletion($prompt, $options)
        );
    }
}
