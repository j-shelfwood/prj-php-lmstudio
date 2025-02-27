<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio;

use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Http\Client;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;
use Shelfwood\LMStudio\Responses\V0\ChatCompletion;
use Shelfwood\LMStudio\Responses\V0\Embedding;
use Shelfwood\LMStudio\Responses\V0\TextCompletion;
use Shelfwood\LMStudio\Traits\HandlesStreamingResponses;

class LMS implements LMStudioClientInterface
{
    use HandlesStreamingResponses;

    protected Client $client;

    protected StreamingResponseHandler $streamingHandler;

    private string $apiVersion = 'api/v0';

    public function __construct(LMStudioConfig $config)
    {
        // Use the base URL as is, but ensure paths include api/v0
        $this->client = new Client($config);
        $this->streamingHandler = new StreamingResponseHandler;
    }

    public function models(): array
    {
        return $this->client->get($this->apiVersion.'/models');
    }

    public function chat(array $messages, array $options = []): ChatCompletion
    {
        $response = $this->client->post($this->apiVersion.'/chat/completions', array_merge([
            'messages' => $messages,
        ], $options));

        return ChatCompletion::fromArray($response);
    }

    public function streamChat(array $messages, array $options = []): \Generator
    {
        return $this->client->stream($this->apiVersion.'/chat/completions', array_merge([
            'messages' => $messages,
            'stream' => true,
        ], $options));
    }

    public function completion(string $prompt, array $options = []): TextCompletion
    {
        $response = $this->client->post($this->apiVersion.'/completions', array_merge([
            'prompt' => $prompt,
        ], $options));

        return TextCompletion::fromArray($response);
    }

    public function streamCompletion(string $prompt, array $options = []): \Generator
    {
        return $this->client->stream($this->apiVersion.'/completions', array_merge([
            'prompt' => $prompt,
            'stream' => true,
        ], $options));
    }

    public function embeddings(string|array $input, array $options = []): Embedding
    {
        $input = is_array($input) ? $input : [$input];

        $response = $this->client->post($this->apiVersion.'/embeddings', array_merge([
            'input' => $input,
        ], $options));

        return Embedding::fromArray($response);
    }

    /**
     * Accumulate content from a streaming chat completion.
     *
     * @param  array  $messages  The messages to generate a completion for
     * @param  array  $options  Additional options for the completion
     */
    public function accumulateChatContent(array $messages, array $options = []): string
    {
        return $this->streamingHandler->accumulateContent(
            $this->streamChat($messages, $options)
        );
    }

    /**
     * Accumulate tool calls from a streaming chat completion.
     *
     * @param  array  $messages  The messages to generate a completion for
     * @param  array  $options  Additional options for the completion
     */
    public function accumulateChatToolCalls(array $messages, array $options = []): array
    {
        return $this->streamingHandler->accumulateToolCalls(
            $this->streamChat($messages, $options)
        );
    }

    /**
     * Accumulate content from a streaming text completion.
     *
     * @param  string  $prompt  The prompt to generate a completion for
     * @param  array  $options  Additional options for the completion
     */
    public function accumulateCompletionContent(string $prompt, array $options = []): string
    {
        return $this->streamingHandler->accumulateContent(
            $this->streamCompletion($prompt, $options)
        );
    }
}
