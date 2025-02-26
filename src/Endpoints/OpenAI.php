<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Endpoints;

use Shelfwood\LMStudio\Contracts\OpenAIClientInterface;
use Shelfwood\LMStudio\DTOs\Common\Chat\Message;
use Shelfwood\LMStudio\DTOs\Common\Tool\ToolCall;
use Shelfwood\LMStudio\DTOs\OpenAI\Model\ModelInfo;
use Shelfwood\LMStudio\DTOs\OpenAI\Model\ModelList;
use Shelfwood\LMStudio\DTOs\OpenAI\Response\ChatCompletion;
use Shelfwood\LMStudio\DTOs\OpenAI\Response\Embedding;
use Shelfwood\LMStudio\DTOs\OpenAI\Response\TextCompletion;
use Shelfwood\LMStudio\Exceptions\ValidationException;

/**
 * LMStudio's OpenAI Compatibility API endpoints
 */
class OpenAI extends APIGate implements OpenAIClientInterface
{
    /**
     * List all available models
     *
     * @throws ConnectionException
     */
    public function listModels(): ModelList
    {
        $data = $this->client->get('/v1/models');

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

        $data = $this->client->get(
            uri: "/v1/models/{$model}"
        );

        return ModelInfo::fromArray(data: $data);
    }

    /**
     * Send a chat completion request.
     *
     * @param  array<Message>  $messages  Array of message objects (system, user, assistant, tool)
     * @param  ?string  $model  The model to use, defaults to the one in config
     * @param  array<ToolCall>  $tools  Array of tools the model can use
     * @param  array<string, mixed>  $options  Additional options:
     *                                         - temperature: (float) Sampling temperature.
     *                                         - top_p: (float) Nucleus sampling parameter.
     *                                         - top_k: (int) Top-k sampling parameter.
     *                                         - max_tokens: (int) Max tokens to generate (-1 for model default).
     *                                         - stop: (array|string) Stop sequences.
     *                                         - presence_penalty: (float) Presence penalty.
     *                                         - frequency_penalty: (float) Frequency penalty.
     *                                         - logit_bias: (array) Token biases.
     *                                         - response_format: (array) Structured output format.
     *
     * @throws ValidationException|ConnectionException
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
            'stream' => false,
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

        $response = $this->client->post(
            uri: '/v1/chat/completions',
            options: [
                'json' => $parameters,
            ]
        );

        // Always return a ChatCompletion object
        return ChatCompletion::fromArray($response);
    }

    /**
     * Create a streaming chat completion.
     *
     * @param  array<Message>  $messages
     * @param  array<ToolCall>  $tools
     * @param  array<string, mixed>  $options
     * @return \Generator<int, ChatCompletion, mixed, void>
     *
     * @throws ValidationException|ConnectionException
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
            'stream' => true,
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

        $response = $this->client->postStreaming(
            uri: '/v1/chat/completions',
            options: [
                'json' => $parameters,
            ]
        );

        return $this->streamingHandler->handle($response);
    }

    /**
     * Send a text completion request.
     *
     * @param  string  $prompt  The prompt to complete
     * @param  ?string  $model  The model to use, defaults to the one in config
     * @param  array<string, mixed>  $options  Additional options (see createChatCompletion)
     *
     * @throws ValidationException|ConnectionException
     */
    public function createTextCompletion(
        string $prompt,
        ?string $model = null,
        array $options = []
    ): TextCompletion {
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
            'prompt' => $prompt,
            'model' => $model,
            'temperature' => $this->config->temperature,
            'max_tokens' => $this->config->maxTokens,
        ], $options);

        $response = $this->client->post(
            uri: '/v1/completions',
            options: [
                'json' => $parameters,
            ]
        );

        return TextCompletion::fromArray($response);
    }

    /**
     * Create a streaming text completion.
     *
     * @param  array<string, mixed>  $options
     * @return \Generator<int, TextCompletion, mixed, void>
     *
     * @throws ValidationException|ConnectionException
     */
    public function createTextCompletionStream(
        string $prompt,
        ?string $model = null,
        array $options = []
    ): \Generator {
        if (empty($prompt)) {
            throw ValidationException::invalidMessage(
                message: 'Prompt cannot be empty'
            );
        }

        $parameters = array_merge([
            'prompt' => $prompt,
            'model' => $model,
            'temperature' => $this->config->temperature,
            'max_tokens' => $this->config->maxTokens,
            'stream' => true,
        ], $options);

        $response = $this->client->postStreaming(
            uri: '/v1/completions',
            options: [
                'json' => $parameters,
            ]
        );

        return $this->streamingHandler->handle($response);
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
            uri: '/v1/embeddings',
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
            uri: '/v1/embeddings',
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
