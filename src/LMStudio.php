<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio;

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
    private ApiClient $apiClient;

    private StreamingResponseHandler $streamingHandler;

    private readonly Config $config;

    public function __construct(
        string|Config $config = 'localhost',
        ?int $port = null,
        ?int $timeout = null
    ) {
        $this->config = is_string($config)
            ? new Config(
                host: $config,
                port: $port ?? 1234,
                timeout: $timeout ?? 30
            )
            : $config;

        $this->apiClient = new ApiClient([
            'base_uri' => "http://{$this->config->host}:{$this->config->port}",
            'timeout' => $this->config->timeout,
        ]);

        $this->streamingHandler = new StreamingResponseHandler;
    }

    public function chat(): ChatBuilder
    {
        return new ChatBuilder($this);
    }

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
     * Send a chat completion request
     *
     * @param  array<Message>  $messages
     * @param  array<ToolCall>  $tools
     *
     * @throws ConnectionException
     */
    public function createChatCompletion(
        array $messages,
        ?string $model = null,
        array $tools = [],
        bool $stream = false
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

        $parameters = [
            'model' => $model,
            'messages' => array_map(
                callback: fn (Message $m) => $m->jsonSerialize(),
                array: $messages
            ),
            'temperature' => $this->config->temperature,
            'max_tokens' => $this->config->maxTokens,
            'stream' => $stream,
            'tools' => empty($tools) ? [] : array_map(
                callback: fn (ToolCall $t) => $t->jsonSerialize(),
                array: $tools
            ),
        ];

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

    public function getClient(): ApiClient
    {
        return $this->apiClient;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}
