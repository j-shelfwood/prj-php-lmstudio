<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Contracts;

use Generator;
use Shelfwood\LMStudio\DTOs\LMStudio\Model\ModelInfo;
use Shelfwood\LMStudio\DTOs\LMStudio\Model\ModelList;
use Shelfwood\LMStudio\DTOs\LMStudio\Response\ChatCompletion;
use Shelfwood\LMStudio\DTOs\LMStudio\Response\Embedding;
use Shelfwood\LMStudio\DTOs\LMStudio\Response\TextCompletion;

interface LMStudioClientInterface
{
    /**
     * List all available models
     */
    public function listModels(): ModelList;

    /**
     * Get model information
     */
    public function getModel(string $model): ModelInfo;

    /**
     * Send a chat completion request.
     *
     * @param  array  $messages  Array of message objects (system, user, assistant, tool)
     * @param  ?string  $model  The model to use, defaults to the one in config
     * @param  array  $options  Additional options
     */
    public function createChatCompletion(
        array $messages,
        ?string $model = null,
        array $options = []
    ): ChatCompletion;

    /**
     * Send a streaming chat completion request.
     *
     * @param  array  $messages  Array of message objects (system, user, assistant, tool)
     * @param  ?string  $model  The model to use, defaults to the one in config
     * @param  array  $options  Additional options
     */
    public function createChatCompletionStream(
        array $messages,
        ?string $model = null,
        array $options = []
    ): Generator;

    /**
     * Send a text completion request.
     *
     * @param  string  $prompt  The prompt to complete
     * @param  ?string  $model  The model to use, defaults to the one in config
     * @param  array  $options  Additional options
     */
    public function createTextCompletion(
        string $prompt,
        ?string $model = null,
        array $options = []
    ): TextCompletion;

    /**
     * Send a streaming text completion request.
     *
     * @param  string  $prompt  The prompt to complete
     * @param  ?string  $model  The model to use, defaults to the one in config
     * @param  array  $options  Additional options
     */
    public function createTextCompletionStream(
        string $prompt,
        ?string $model = null,
        array $options = []
    ): Generator;

    /**
     * Create embeddings for the given text.
     *
     * @param  string|array<string>  $input  The text to embed
     * @param  ?string  $model  The model to use, defaults to the one in config
     */
    public function createEmbedding(
        string|array $input,
        ?string $model = null
    ): Embedding;

    /**
     * Create embeddings for the given text (streaming).
     *
     * @param  string|array<string>  $input  The text to embed
     * @param  ?string  $model  The model to use, defaults to the one in config
     */
    public function createEmbeddingStream(string|array $input, ?string $model = null): Generator;
}
