<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Endpoints;

use Shelfwood\LMStudio\Contracts\ApiClientInterface;
use Shelfwood\LMStudio\Contracts\StreamingResponseHandlerInterface;
use Shelfwood\LMStudio\DTOs\Common\Chat\Message;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\DTOs\Common\Response\BaseChatCompletion;
use Shelfwood\LMStudio\DTOs\Common\Response\BaseTextCompletion;
use Shelfwood\LMStudio\Http\ApiClient;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;
use Shelfwood\LMStudio\Support\ChatBuilder;
use Shelfwood\LMStudio\Support\StructuredOutputBuilder;

abstract class APIGate
{
    protected ApiClientInterface $client;

    protected StreamingResponseHandlerInterface $streamingHandler;

    protected Config $config;

    public function __construct(
        Config $config,
        ?ApiClientInterface $client = null,
        ?StreamingResponseHandlerInterface $streamingHandler = null
    ) {
        $this->config = $config;

        // Default implementations if not provided
        $this->client = $client ?? new ApiClient([
            'base_uri' => "http://{$this->config->host}:{$this->config->port}",
            'timeout' => $this->config->timeout,
        ]);

        $this->streamingHandler = $streamingHandler ?? new StreamingResponseHandler;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Chat builder
     */
    public function chat(): ChatBuilder
    {
        return new ChatBuilder($this);
    }

    /**
     * Structured output builder
     */
    public function structuredOutput(): StructuredOutputBuilder
    {
        return new StructuredOutputBuilder($this);
    }

    /**
     * Create a chat completion.
     *
     * @param  array<Message>  $messages
     */
    abstract public function createChatCompletion(
        array $messages,
        ?string $model = null,
        array $options = []
    ): BaseChatCompletion;

    /**
     * Create a streaming chat completion.
     *
     * @param  array<Message>  $messages
     * @return \Generator<int, BaseChatCompletion, mixed, void>
     */
    abstract public function createChatCompletionStream(
        array $messages,
        ?string $model = null,
        array $options = []
    ): \Generator;

    /**
     * Create a text completion.
     */
    abstract public function createTextCompletion(
        string $prompt,
        ?string $model = null,
        array $options = []
    ): BaseTextCompletion;
}
