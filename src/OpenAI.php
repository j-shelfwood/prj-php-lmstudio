<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio;

use Generator;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Contracts\AbstractLMStudioClient;
use Shelfwood\LMStudio\Http\Requests\Common\RequestInterface;
use Shelfwood\LMStudio\Http\Requests\V1\ChatCompletionRequest;
use Shelfwood\LMStudio\Http\Requests\V1\EmbeddingRequest;
use Shelfwood\LMStudio\Http\Requests\V1\TextCompletionRequest;
use Shelfwood\LMStudio\Http\Responses\V1\ChatCompletion;
use Shelfwood\LMStudio\Http\Responses\V1\Embedding;
use Shelfwood\LMStudio\Http\Responses\V1\TextCompletion;

class OpenAI extends AbstractLMStudioClient
{
    public function __construct(LMStudioConfig $config)
    {
        parent::__construct($config);
        $this->apiVersion = 'v1';
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
}
