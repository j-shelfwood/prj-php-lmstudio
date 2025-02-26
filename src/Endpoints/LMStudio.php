<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Endpoints;

use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\DTOs\Common\Chat\Message;
use Shelfwood\LMStudio\DTOs\Common\Tool\ToolCall;
use Shelfwood\LMStudio\DTOs\LMStudio\Model\ModelInfo;
use Shelfwood\LMStudio\DTOs\LMStudio\Model\ModelList;
use Shelfwood\LMStudio\DTOs\LMStudio\Response\ChatCompletion;
use Shelfwood\LMStudio\DTOs\LMStudio\Response\Embedding;
use Shelfwood\LMStudio\DTOs\LMStudio\Response\TextCompletion;
use Shelfwood\LMStudio\Exceptions\ValidationException;

/**
 * LMStudio's API endpoints
 */
class LMStudio extends APIGate implements LMStudioClientInterface
{
    /**
     * List all available models via the REST API
     *
     * @throws ConnectionException
     */
    public function listModels(): ModelList
    {
        $response = $this->client->get('/api/v0/models');

        return ModelList::fromArray($response);
    }

    /**
     * Get information about a specific model via the REST API
     *
     * @throws ConnectionException|ValidationException
     */
    public function getModel(string $model): ModelInfo
    {
        if (empty($model)) {
            throw ValidationException::invalidModel(
                message: 'Model identifier cannot be empty for REST API'
            );
        }

        return ModelInfo::fromArray($this->client->get("/api/v0/models/{$model}"));
    }

    /**
     * Create a chat completion via the REST API
     *
     * @param  array<Message>  $messages
     * @param  array<ToolCall>  $tools
     *
     * @throws ConnectionException|ValidationException
     */
    public function createChatCompletion(
        array $messages,
        ?string $model = null,
        array $tools = [],
        array $options = []
    ): ChatCompletion {
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

        $parameters = array_merge([
            'model' => $model,
            'messages' => array_map(fn (Message $m) => $m->jsonSerialize(), $messages),
            'temperature' => $this->config->temperature,
            'max_tokens' => $this->config->maxTokens,
            'stream' => $options['stream'] ?? false,
            'tools' => empty($tools) ? [] : array_map(function ($tool) {
                return $tool instanceof ToolCall ? $tool->jsonSerialize() : $tool;
            }, $tools),
        ], $options);

        $response = $this->client->post(
            uri: '/api/v0/chat/completions',
            options: [
                'json' => $parameters,
            ]
        );

        return ChatCompletion::fromArray($response);
    }

    /**
     * Create a chat completion via the REST API
     *
     * @param  array<Message>  $messages
     * @param  array<string, mixed>  $options
     * @return \Generator<int, ChatCompletion, mixed, void>
     *
     * @throws ConnectionException|ValidationException
     */
    public function createChatCompletionStream(
        array $messages,
        ?string $model = null,
        array $tools = [],
        array $options = []
    ): \Generator {
        $model = $model ?? $this->config->defaultModel;

        if (empty($model)) {
            throw ValidationException::invalidModel(
                message: 'Model must be specified for chat completion'
            );
        }

        $parameters = array_merge([
            'model' => $model,
            'messages' => array_map(fn (Message $m) => $m->jsonSerialize(), $messages),
            'temperature' => $this->config->temperature,
            'max_tokens' => $this->config->maxTokens,
            'stream' => true,
            'tools' => empty($tools) ? [] : array_map(function ($tool) {
                return $tool instanceof ToolCall ? $tool->jsonSerialize() : $tool;
            }, $tools),
        ], $options);

        $response = $this->client->postStreaming(
            uri: '/api/v0/chat/completions',
            options: [
                'json' => $parameters,
            ]
        );

        return $this->streamingHandler->handle($response);
    }

    /**
     * Create a text completion via the REST API
     *
     * @param  array<string, mixed>  $options
     *
     * @throws ConnectionException|ValidationException
     */
    public function createTextCompletion(string $prompt, ?string $model = null, array $options = []): TextCompletion
    {
        $model = $model ?? $this->config->defaultModel;

        if (empty($model)) {
            throw ValidationException::invalidModel(
                message: 'Model must be specified for completion'
            );
        }

        $parameters = array_merge([
            'prompt' => $prompt,
            'model' => $model,
            'temperature' => $this->config->temperature,
            'max_tokens' => $this->config->maxTokens,
        ], $options);

        $response = $this->client->post(
            uri: '/api/v0/completions',
            options: [
                'json' => $parameters,
            ]
        );

        return TextCompletion::fromArray($response);
    }

    /**
     * Create a text completion via the REST API
     *
     * @param  array<string, mixed>  $options
     *
     * @throws ConnectionException|ValidationException
     */
    public function createTextCompletionStream(string $prompt, ?string $model = null, array $options = []): \Generator
    {
        $model = $model ?? $this->config->defaultModel;

        if (empty($model)) {
            throw ValidationException::invalidModel(
                message: 'Model must be specified for completion'
            );
        }

        $parameters = array_merge([
            'prompt' => $prompt,
            'model' => $model,
            'temperature' => $this->config->temperature,
            'max_tokens' => $this->config->maxTokens,
        ], $options);

        $response = $this->client->postStreaming(
            uri: '/api/v0/completions',
            options: [
                'json' => $parameters,
            ]
        );

        return $this->streamingHandler->handle($response);
    }

    /**
     * Create text embeddings via the REST API
     *
     * @throws ConnectionException|ValidationException
     */
    public function createEmbeddings(string $model, string|array $input): Embedding
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

        $response = $this->client->post(
            uri: '/api/v0/embeddings',
            options: [
                'json' => [
                    'model' => $model,
                    'input' => $input,
                ],
            ]
        );

        return Embedding::fromArray($response);
    }

    /**
     * Create embeddings for a text.
     *
     * @param  string|array<string>  $input  The text(s) to embed
     * @param  ?string  $model  The model to use, defaults to the one in config
     *
     * @throws ValidationException|ConnectionException
     */
    public function createEmbedding(
        string|array $input,
        ?string $model = null
    ): Embedding {
        $model = $model ?? $this->config->defaultModel;

        if (empty($model)) {
            throw ValidationException::invalidModel(
                message: 'Model must be specified for embeddings'
            );
        }

        if (empty($input)) {
            throw ValidationException::invalidInput(
                message: 'Input text cannot be empty'
            );
        }

        $response = $this->client->post(
            uri: '/api/v0/embeddings',
            options: [
                'json' => [
                    'model' => $model,
                    'input' => $input,
                ],
            ]
        );

        return Embedding::fromArray($response);
    }

    /**
     * Create embeddings for a text (streaming).
     *
     * @param  string|array<string>  $input  The text(s) to embed
     * @param  ?string  $model  The model to use, defaults to the one in config
     * @return \Generator<int, Embedding, mixed, void>
     *
     * @throws ValidationException|ConnectionException
     */
    public function createEmbeddingStream(
        string|array $input,
        ?string $model = null
    ): \Generator {
        $model = $model ?? $this->config->defaultModel;

        if (empty($model)) {
            throw ValidationException::invalidModel(
                message: 'Model must be specified for embeddings'
            );
        }

        if (empty($input)) {
            throw ValidationException::invalidInput(
                message: 'Input text cannot be empty'
            );
        }

        $response = $this->client->postStreaming(
            uri: '/api/v0/embeddings',
            options: [
                'json' => [
                    'model' => $model,
                    'input' => $input,
                ],
            ]
        );

        return $this->streamingHandler->handle($response);
    }
}
