<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio;

use Generator;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Contracts\AbstractLMStudioClient;
use Shelfwood\LMStudio\Exceptions\ApiRequestException;
use Shelfwood\LMStudio\Http\Requests\Common\RequestInterface;
use Shelfwood\LMStudio\Http\Responses\V0\ChatCompletion;
use Shelfwood\LMStudio\Http\Responses\V0\Embedding;
use Shelfwood\LMStudio\Http\Responses\V0\TextCompletion;

/**
 * Client for the native LM Studio API (v0).
 *
 * This class provides methods for interacting with the LM Studio API,
 * including chat completions, text completions, and embeddings.
 *
 * Example usage:
 * ```php
 * $config = new LMStudioConfig();
 * $client = new LMS($config);
 * $response = $client->chatCompletion($request);
 * ```
 */
class LMS extends AbstractLMStudioClient
{
    /**
     * Create a new LMS client instance.
     *
     * @param  LMStudioConfig  $config  The client configuration
     */
    public function __construct(LMStudioConfig $config)
    {
        parent::__construct($config);
        $this->apiVersion = 'api/v0';
    }

    /**
     * Create a chat completion using a request object.
     *
     * @param  RequestInterface  $request  The chat completion request
     * @return ChatCompletion The chat completion response
     *
     * @throws ApiRequestException If the API request fails
     */
    public function chatCompletion(RequestInterface $request): ChatCompletion
    {
        return $this->processRequest(
            $request,
            '/chat/completions',
            ChatCompletion::class
        );
    }

    /**
     * Create a streaming chat completion using a request object.
     *
     * @param  RequestInterface  $request  The chat completion request
     * @return Generator<\Shelfwood\LMStudio\ValueObjects\StreamChunk> A generator yielding stream chunks
     *
     * @throws ApiRequestException If the API request fails
     */
    public function streamChatCompletion(RequestInterface $request): Generator
    {
        return $this->streamRequest($request, '/chat/completions');
    }

    /**
     * Create a text completion using a request object.
     *
     * @param  RequestInterface  $request  The text completion request
     * @return TextCompletion The text completion response
     *
     * @throws ApiRequestException If the API request fails
     */
    public function textCompletion(RequestInterface $request): TextCompletion
    {
        return $this->processRequest(
            $request,
            '/completions',
            TextCompletion::class
        );
    }

    /**
     * Create a streaming text completion using a request object.
     *
     * @param  RequestInterface  $request  The text completion request
     * @return Generator<\Shelfwood\LMStudio\ValueObjects\StreamChunk> A generator yielding stream chunks
     *
     * @throws ApiRequestException If the API request fails
     */
    public function streamTextCompletion(RequestInterface $request): Generator
    {
        return $this->streamRequest($request, '/completions');
    }

    /**
     * Create embeddings using a request object.
     *
     * @param  RequestInterface  $request  The embedding request
     * @return Embedding The embedding response
     *
     * @throws ApiRequestException If the API request fails
     */
    public function createEmbeddings(RequestInterface $request): Embedding
    {
        return $this->processRequest(
            $request,
            '/embeddings',
            Embedding::class
        );
    }
}
