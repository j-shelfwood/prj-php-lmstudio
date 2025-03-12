<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Contract;

use Generator;
use GuzzleHttp\Psr7\Utils;
use Shelfwood\LMStudio\Core\Config\LMStudioConfig;
use Shelfwood\LMStudio\Exception\StreamingException;
use Shelfwood\LMStudio\Http\Client;
use Shelfwood\LMStudio\Http\Request\Common\RequestInterface;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;
use Shelfwood\LMStudio\ValueObject\ChatHistory;

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
     *
     * @deprecated Use chatCompletion() with a ChatCompletionRequest instead
     */
    public function chat(array $messages, array $options = []): mixed
    {
        // Get the model from options or use default
        $model = $options['model'] ?? $this->config->getDefaultModel() ?? 'qwen2.5-7b-instruct-1m';

        // Create a request object from the messages and options
        $requestClass = "\\Shelfwood\\LMStudio\\Http\\Requests\\{$this->getApiVersionNamespace()}\\ChatCompletionRequest";
        $request = new $requestClass($messages, $model);

        foreach ($options as $key => $value) {
            if ($key === 'model') {
                continue; // Already set in constructor
            }

            $setter = 'set'.ucfirst($key);

            if (method_exists($request, $setter)) {
                $request->$setter($value);
            }
        }

        return $this->chatCompletion($request);
    }

    /**
     * Create a streaming chat completion (legacy method).
     *
     * @return Generator<\Shelfwood\LMStudio\ValueObjects\StreamChunk>
     *
     * @deprecated Use streamChatCompletion() with a ChatCompletionRequest instead
     */
    public function streamChat(array $messages, array $options = []): Generator
    {
        // Get the model from options or use default
        $model = $options['model'] ?? $this->config->getDefaultModel() ?? 'qwen2.5-7b-instruct-1m';

        // Create a request object from the messages and options
        $requestClass = "\\Shelfwood\\LMStudio\\Http\\Requests\\{$this->getApiVersionNamespace()}\\ChatCompletionRequest";
        $request = new $requestClass($messages, $model);
        $request->setStream(true);

        foreach ($options as $key => $value) {
            if ($key === 'model') {
                continue; // Already set in constructor
            }

            $setter = 'set'.ucfirst($key);

            if (method_exists($request, $setter)) {
                $request->$setter($value);
            }
        }

        return $this->streamChatCompletion($request);
    }

    /**
     * Create a completion (legacy method).
     *
     * @deprecated Use textCompletion() with a TextCompletionRequest instead
     */
    public function completion(string $prompt, array $options = []): mixed
    {
        // Get the model from options or use default
        $model = $options['model'] ?? $this->config->getDefaultModel() ?? 'qwen2.5-7b-instruct-1m-instruct';

        // Create a request object from the prompt and options
        $requestClass = "\\Shelfwood\\LMStudio\\Http\\Requests\\{$this->getApiVersionNamespace()}\\TextCompletionRequest";
        $request = new $requestClass($prompt, $model);

        foreach ($options as $key => $value) {
            if ($key === 'model') {
                continue; // Already set in constructor
            }

            $setter = 'set'.ucfirst($key);

            if (method_exists($request, $setter)) {
                $request->$setter($value);
            }
        }

        return $this->textCompletion($request);
    }

    /**
     * Create a streaming completion (legacy method).
     *
     * @return Generator<\Shelfwood\LMStudio\ValueObjects\StreamChunk>
     *
     * @deprecated Use streamTextCompletion() with a TextCompletionRequest instead
     */
    public function streamCompletion(string $prompt, array $options = []): Generator
    {
        // Get the model from options or use default
        $model = $options['model'] ?? $this->config->getDefaultModel() ?? 'qwen2.5-7b-instruct-1m-instruct';

        // Create a request object from the prompt and options
        $requestClass = "\\Shelfwood\\LMStudio\\Http\\Requests\\{$this->getApiVersionNamespace()}\\TextCompletionRequest";
        $request = new $requestClass($prompt, $model);
        $request->setStream(true);

        foreach ($options as $key => $value) {
            if ($key === 'model') {
                continue; // Already set in constructor
            }

            $setter = 'set'.ucfirst($key);

            if (method_exists($request, $setter)) {
                $request->$setter($value);
            }
        }

        return $this->streamTextCompletion($request);
    }

    /**
     * Create embeddings (legacy method).
     *
     * @deprecated Use createEmbeddings() with an EmbeddingRequest instead
     */
    public function embeddings(string|array $input, array $options = []): mixed
    {
        // Get the model from options or use default
        $model = $options['model'] ?? $this->config->getDefaultModel() ?? 'text-embedding-ada-002';

        // Create a request object from the input and options
        $requestClass = "\\Shelfwood\\LMStudio\\Http\\Requests\\{$this->getApiVersionNamespace()}\\EmbeddingRequest";
        $request = new $requestClass($input, $model);

        foreach ($options as $key => $value) {
            if ($key === 'model') {
                continue; // Already set in constructor
            }

            $setter = 'set'.ucfirst($key);

            if (method_exists($request, $setter)) {
                $request->$setter($value);
            }
        }

        return $this->createEmbeddings($request);
    }

    /**
     * Accumulate content from a streaming chat completion.
     *
     * @param  array|ChatHistory  $messages  The messages to generate a completion for
     * @param  array  $options  Additional options for the completion
     *
     * @deprecated Use streamChatCompletion() and process the stream directly
     */
    public function accumulateChatContent(array|ChatHistory $messages, array $options = []): string
    {
        $messages = $messages instanceof ChatHistory ? $messages->jsonSerialize() : $messages;
        $stream = $this->streamChat($messages, $options);

        return $this->getStreamingHandler()->accumulateContent($stream);
    }

    /**
     * Accumulate tool calls from a streaming chat completion.
     *
     * @param  array|ChatHistory  $messages  The messages to generate a completion for
     * @param  array  $options  Additional options for the completion
     *
     * @deprecated Use streamChatCompletion() and process the stream directly
     */
    public function accumulateChatToolCalls(array|ChatHistory $messages, array $options = []): array
    {
        $messages = $messages instanceof ChatHistory ? $messages->jsonSerialize() : $messages;
        $stream = $this->streamChat($messages, $options);

        return $this->getStreamingHandler()->accumulateToolCalls($stream);
    }

    /**
     * Accumulate content from a streaming text completion.
     *
     * @param  string  $prompt  The prompt to generate a completion for
     * @param  array  $options  Additional options for the completion
     *
     * @deprecated Use streamTextCompletion() and process the stream directly
     */
    public function accumulateCompletionContent(string $prompt, array $options = []): string
    {
        $stream = $this->streamCompletion($prompt, $options);

        return $this->getStreamingHandler()->accumulateContent($stream);
    }

    /**
     * Get the streaming handler.
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

    /**
     * Send a streaming request to the API.
     *
     * @param  RequestInterface  $request  The request object
     * @param  string  $endpoint  The API endpoint
     * @return Generator<\Shelfwood\LMStudio\ValueObjects\StreamChunk>
     *
     * @throws StreamingException If the request fails
     */
    protected function streamRequest(RequestInterface $request, string $endpoint): Generator
    {
        // Perform health check if enabled
        if ($this->config->isHealthCheckEnabled()) {
            if (! $this->client->checkHealth()) {
                throw new StreamingException('LMStudio server is not available');
            }
        }

        // Ensure streaming is enabled
        $data = $request->toArray();
        $data['stream'] = true;

        return $this->client->stream($this->apiVersion.$endpoint, $data);
    }

    /**
     * Process a request and return the appropriate response object.
     *
     * @param  RequestInterface  $request  The request object
     * @param  string  $endpoint  The API endpoint
     * @param  string  $responseClass  The fully qualified class name of the response object
     * @return mixed The response object
     */
    protected function processRequest(RequestInterface $request, string $endpoint, string $responseClass): mixed
    {
        // Perform health check if enabled
        if ($this->config->isHealthCheckEnabled()) {
            if (! $this->client->checkHealth()) {
                throw new \Shelfwood\LMStudio\Exceptions\LMStudioException('LMStudio server is not available');
            }
        }

        $response = $this->client->post(
            $this->apiVersion.$endpoint,
            $request->toArray()
        );

        return $responseClass::fromArray($response);
    }

    /**
     * Get the API version namespace for request/response classes.
     *
     * @return string The API version namespace (V0 or V1)
     */
    public function getApiVersionNamespace(): string
    {
        return str_starts_with($this->apiVersion, 'api/v0') ? 'V0' : 'V1';
    }
}
