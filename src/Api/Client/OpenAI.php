<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Client;

use Generator;
use Shelfwood\LMStudio\Core\Config\LMStudioConfig;
use Shelfwood\LMStudio\Api\Contract\AbstractLMStudioClient;
use Shelfwood\LMStudio\Exception\ApiRequestException;
use Shelfwood\LMStudio\Http\Request\Common\RequestInterface;
use Shelfwood\LMStudio\Http\Response\V1\ChatCompletion;
use Shelfwood\LMStudio\Http\Response\V1\Embedding;
use Shelfwood\LMStudio\Http\Response\V1\TextCompletion;

/**
 * Client for the OpenAI compatibility API (v1).
 *
 * This class provides methods for interacting with the LM Studio API
 * using the OpenAI compatibility endpoints, including chat completions,
 * text completions, and embeddings.
 *
 * Example usage:
 * ```php
 * $config = new LMStudioConfig();
 * $client = new OpenAI($config);
 * $response = $client->chatCompletion($request);
 * ```
 */
class OpenAI extends AbstractLMStudioClient
{
    /**
     * Create a new OpenAI client instance.
     *
     * @param  LMStudioConfig  $config  The client configuration
     */
    public function __construct(LMStudioConfig $config)
    {
        parent::__construct($config);
        $this->apiVersion = 'v1';
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
