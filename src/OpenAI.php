<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio;

use Generator;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Contracts\AbstractLMStudioClient;
use Shelfwood\LMStudio\Http\Requests\Common\RequestInterface;
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
        return $this->processRequest(
            $request,
            '/chat/completions',
            ChatCompletion::class
        );
    }

    /**
     * Create a streaming chat completion using a request object.
     *
     * @return Generator<\Shelfwood\LMStudio\ValueObjects\StreamChunk>
     */
    public function streamChatCompletion(RequestInterface $request): Generator
    {
        return $this->streamRequest($request, '/chat/completions');
    }

    /**
     * Create a text completion using a request object.
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
     * @return Generator<\Shelfwood\LMStudio\ValueObjects\StreamChunk>
     */
    public function streamTextCompletion(RequestInterface $request): Generator
    {
        return $this->streamRequest($request, '/completions');
    }

    /**
     * Create embeddings using a request object.
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
