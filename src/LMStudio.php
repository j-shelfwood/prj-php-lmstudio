<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio;

use Shelfwood\LMStudio\Contracts\ApiClientInterface;
use Shelfwood\LMStudio\Contracts\StreamingResponseHandlerInterface;
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\DTOs\Model\ModelInfo;
use Shelfwood\LMStudio\DTOs\Model\ModelList;
use Shelfwood\LMStudio\DTOs\Tool\ToolCall;
use Shelfwood\LMStudio\Exceptions\ValidationException;
use Shelfwood\LMStudio\Http\ApiClient;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;
use Shelfwood\LMStudio\Support\ChatBuilder;

class LMStudio
{
    private readonly ApiClientInterface $apiClient;

    private readonly StreamingResponseHandlerInterface $streamingHandler;

    private readonly Config $config;

    public function __construct(
        Config $config,
        ?ApiClientInterface $apiClient = null,
        ?StreamingResponseHandlerInterface $streamingHandler = null
    ) {
        $this->config = $config;

        // Default implementations if not provided
        $this->apiClient = $apiClient ?? new ApiClient([
            'base_uri' => "http://{$this->config->host}:{$this->config->port}",
            'timeout' => $this->config->timeout,
        ]);

        $this->streamingHandler = $streamingHandler ?? new StreamingResponseHandler;
    }

    /**
     * Create a new instance with default configuration
     */
    public static function create(
        string $host = 'localhost',
        ?int $port = null,
        ?int $timeout = null
    ): self {
        return new self(new Config(
            host: $host,
            port: $port ?? 1234,
            timeout: $timeout ?? 30
        ));
    }

    public function chat(): ChatBuilder
    {
        return new ChatBuilder($this);
    }

    // -------------------------------
    // OpenAI Compatibility Endpoints (/v1)
    // -------------------------------

    /**
     * List all available models
     *
     * @throws ConnectionException
     */
    public function listModels(): ModelList
    {
        $data = $this->apiClient->get('/v1/models');

        return ModelList::fromArray($data);
    }

    /**
     * Get model information
     *
     * @throws ConnectionException
     */
    public function getModel(string $model): ModelInfo
    {
        if (empty($model)) {
            throw ValidationException::invalidModel(
                message: "Model identifier '{$model}' cannot be empty"
            );
        }

        $data = $this->apiClient->get(
            uri: "/v1/models/{$model}"
        );

        return ModelInfo::fromArray(data: $data);
    }

    /**
     * Send a chat completion request.
     *
     * @param  array<Message>  $messages
     * @param  array<ToolCall>  $tools
     * @param  array  $options  Additional parameters such as:
     *                          - ttl: (int) Time-To-Live in seconds for JIT loading.
     *                          - auto_evict: (bool) Whether to auto-evict previously loaded models.
     *                          - top_p: (float) Nucleus sampling threshold.
     *                          - stop: (string|array) Stop sequences.
     *                          - presence_penalty: (float) Presence penalty.
     *                          - frequency_penalty: (float) Frequency penalty.
     *                          - logit_bias: (array) Token biases.
     *                          - response_format: (array) Structured output format.
     *
     * @throws ValidationException|ConnectionException
     */
    public function createChatCompletion(
        array $messages,
        ?string $model = null,
        array $tools = [],
        bool $stream = false,
        array $options = []
    ): mixed {
        $model = $model ?? $this->config->defaultModel;

        if (empty($model)) {
            throw ValidationException::invalidModel(
                message: 'Model must be specified for chat completion'
            );
        }

        if (empty($messages)) {
            throw ValidationException::invalidMessage(
                message: 'At least one message is required for chat completion'
            );
        }

        // Merge default TTL and auto_evict from config if not provided
        $options = array_merge([
            'ttl' => $this->config->defaultTtl,
            'auto_evict' => $this->config->autoEvict,
        ], $options);

        $parameters = [
            'model' => $model,
            'messages' => array_map(fn (Message $m) => $m->jsonSerialize(), $messages),
            'temperature' => $this->config->temperature,
            'max_tokens' => $this->config->maxTokens,
            'stream' => $stream,
            'tools' => empty($tools) ? [] : array_map(fn (ToolCall $t) => $t->jsonSerialize(), $tools),
        ];

        // Merge additional options (e.g. ttl, auto_evict, top_p, etc.)
        $parameters = array_merge($parameters, $options);

        // Handle tool use mode conversion if needed
        if ($this->config->toolUseMode === 'default') {
            $parameters['messages'] = array_map(function ($message) {
                if (isset($message['role']) && $message['role'] === 'tool') {
                    $message['role'] = 'user';
                    $message['default_tool_call'] = true;
                }

                return $message;
            }, $parameters['messages']);
        }

        $response = $this->apiClient->post(
            uri: '/v1/chat/completions',
            options: [
                'json' => $parameters,
                'stream' => $stream,
            ]
        );

        if ($stream) {
            return $this->streamingHandler->handle(response: $response);
        }

        return $response;
    }

    /**
     * Create text completion via /v1/completions.
     *
     * @param  array  $options  Additional parameters such as:
     *                          - ttl: (int) Time-To-Live in seconds for JIT loading.
     *                          - auto_evict: (bool) Whether to auto-evict previously loaded models.
     *                          - top_p: (float) Nucleus sampling threshold.
     *                          - stop: (string|array) Stop sequences.
     *                          - presence_penalty: (float) Presence penalty.
     *                          - frequency_penalty: (float) Frequency penalty.
     *                          - logit_bias: (array) Token biases.
     *                          - response_format: (array) Structured output format.
     *                          The response_format parameter should follow the structure:
     *                          [
     *                          "type" => "json_schema",
     *                          "json_schema" => [
     *                          "name" => "your_schema_name",
     *                          "strict" => true,
     *                          "schema" => [
     *                          "type" => "object",
     *                          "properties" => [...],
     *                          "required" => [...]
     *                          ]
     *                          ]
     *                          ]
     *
     * @throws ValidationException|ConnectionException
     */
    public function createTextCompletion(
        string $prompt,
        ?string $model = null,
        array $options = []
    ): mixed {
        $model = $model ?? $this->config->defaultModel;

        if (empty($model)) {
            throw ValidationException::invalidModel(
                message: 'Model must be specified for text completion'
            );
        }

        if (empty($prompt)) {
            throw ValidationException::invalidMessage(
                message: 'Prompt cannot be empty'
            );
        }

        // Merge default TTL and auto_evict from config if not provided
        $options = array_merge([
            'ttl' => $this->config->defaultTtl,
            'auto_evict' => $this->config->autoEvict,
        ], $options);

        $parameters = array_merge([
            'model' => $model,
            'prompt' => $prompt,
            'temperature' => $this->config->temperature,
            'max_tokens' => $this->config->maxTokens,
            'stream' => $options['stream'] ?? false,
        ], $options);

        $response = $this->apiClient->post(
            uri: '/v1/completions',
            options: [
                'json' => $parameters,
                'stream' => $parameters['stream'],
            ]
        );

        if ($parameters['stream']) {
            return $this->streamingHandler->handle(response: $response);
        }

        return $response;
    }

    /**
     * Create embeddings for given text
     *
     * @throws ConnectionException
     */
    public function createEmbeddings(string $model, string|array $input): array
    {
        if (empty($model)) {
            throw ValidationException::invalidModel(
                message: 'Model identifier cannot be empty for embeddings'
            );
        }

        if (empty($input)) {
            throw ValidationException::invalidMessage(
                message: 'Input text cannot be empty for embeddings'
            );
        }

        return $this->apiClient->post(
            uri: '/v1/embeddings',
            options: [
                'json' => [
                    'model' => $model,
                    'input' => $input,
                ],
            ]
        );
    }

    // -------------------------------
    // LM Studio REST API Endpoints (/api/v0)
    // -------------------------------

    /**
     * List all available models via the REST API
     *
     * @throws ConnectionException
     */
    public function listRestModels(): array
    {
        return $this->apiClient->get('/api/v0/models');
    }

    /**
     * Get information about a specific model via the REST API
     *
     * @throws ConnectionException|ValidationException
     */
    public function getRestModel(string $model): array
    {
        if (empty($model)) {
            throw ValidationException::invalidModel(
                message: 'Model identifier cannot be empty for REST API'
            );
        }

        return $this->apiClient->get("/api/v0/models/{$model}");
    }

    /**
     * Create a chat completion via the REST API
     *
     * @param  array<Message>  $messages
     * @param  array<ToolCall>  $tools
     *
     * @throws ConnectionException|ValidationException
     */
    public function createRestChatCompletion(
        array $messages,
        ?string $model = null,
        array $tools = [],
        array $options = []
    ): mixed {
        $model = $model ?? $this->config->defaultModel;

        if (empty($model)) {
            throw ValidationException::invalidModel(
                message: 'Model must be specified for REST chat completion'
            );
        }

        if (empty($messages)) {
            throw ValidationException::invalidMessage(
                message: 'At least one message is required for REST chat completion'
            );
        }

        $parameters = array_merge([
            'model' => $model,
            'messages' => array_map(
                callback: fn (Message $m) => $m->jsonSerialize(),
                array: $messages
            ),
            'temperature' => $this->config->temperature,
            'max_tokens' => $this->config->maxTokens,
            'stream' => $options['stream'] ?? false,
            'tools' => empty($tools) ? [] : array_map(
                callback: fn (ToolCall $t) => $t->jsonSerialize(),
                array: $tools
            ),
        ], $options);

        $response = $this->apiClient->post(
            uri: '/api/v0/chat/completions',
            options: [
                'json' => $parameters,
                'stream' => $parameters['stream'],
            ]
        );

        if ($parameters['stream']) {
            return $this->streamingHandler->handle(response: $response);
        }

        return $response;
    }

    /**
     * Create a text completion via the REST API
     *
     * @throws ConnectionException|ValidationException
     */
    public function createRestCompletion(string $prompt, ?string $model = null, array $options = []): mixed
    {
        $model = $model ?? $this->config->defaultModel;

        if (empty($model)) {
            throw ValidationException::invalidModel(
                message: 'Model must be specified for REST completion'
            );
        }

        if (empty($prompt)) {
            throw ValidationException::invalidMessage(
                message: 'Prompt cannot be empty for REST completion'
            );
        }

        $parameters = array_merge([
            'model' => $model,
            'prompt' => $prompt,
            'temperature' => $this->config->temperature,
            'max_tokens' => $this->config->maxTokens,
            'stream' => $options['stream'] ?? false,
        ], $options);

        $response = $this->apiClient->post(
            uri: '/api/v0/completions',
            options: [
                'json' => $parameters,
                'stream' => $parameters['stream'],
            ]
        );

        if ($parameters['stream']) {
            return $this->streamingHandler->handle(response: $response);
        }

        return $response;
    }

    /**
     * Create text embeddings via the REST API
     *
     * @throws ConnectionException|ValidationException
     */
    public function createRestEmbeddings(string $model, string|array $input): array
    {
        if (empty($model)) {
            throw ValidationException::invalidModel(
                message: 'Model identifier cannot be empty for REST embeddings'
            );
        }

        if (empty($input)) {
            throw ValidationException::invalidMessage(
                message: 'Input cannot be empty for REST embeddings'
            );
        }

        return $this->apiClient->post(
            uri: '/api/v0/embeddings',
            options: [
                'json' => [
                    'model' => $model,
                    'input' => $input,
                ],
            ]
        );
    }

    public function getClient(): ApiClientInterface
    {
        return $this->apiClient;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}
