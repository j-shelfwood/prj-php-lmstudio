<?php

namespace Shelfwood\LMStudio;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shelfwood\LMStudio\Support\ChatBuilder;

class LMStudio
{
    protected Client $client;

    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = new Client([
            'base_uri' => sprintf('http://%s:%s/',
                $config['host'] ?? 'localhost',
                $config['port'] ?? 1234
            ),
            'timeout' => $config['timeout'] ?? 60,
        ]);
    }

    /**
     * Start a new chat interaction
     */
    public function chat(): ChatBuilder
    {
        return new ChatBuilder($this);
    }

    /**
     * List all available models
     *
     * @throws GuzzleException
     */
    public function listModels(): array
    {
        $response = $this->client->get('v1/models');

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get model information
     *
     * @throws GuzzleException
     */
    public function getModel(string $model): array
    {
        $response = $this->client->get("api/v0/models/{$model}");

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Send a chat completion request
     *
     * @throws GuzzleException
     */
    public function createChatCompletion(array $parameters): mixed
    {
        $options = [
            'json' => array_merge([
                'temperature' => $this->config['temperature'] ?? 0.7,
                'max_tokens' => $this->config['max_tokens'] ?? -1,
            ], $parameters),
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'http_errors' => false, // Don't throw exceptions on 4xx/5xx
            'connect_timeout' => 5,
            'timeout' => 0, // No timeout for streaming responses
        ];

        if ($parameters['stream'] ?? false) {
            $options['stream'] = true;
            $options['headers']['Accept'] = 'text/event-stream';
            $options['headers']['Cache-Control'] = 'no-cache';
            $options['decode_content'] = true;

            $response = $this->client->post('v1/chat/completions', $options);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Error from LMStudio API: '.$response->getBody()->getContents());
            }

            return $this->streamResponse($response);
        }

        $response = $this->client->post('v1/chat/completions', $options);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Error from LMStudio API: '.$response->getBody()->getContents());
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Stream the response body
     */
    protected function streamResponse($response): \Generator
    {
        $buffer = '';
        $stream = $response->getBody();
        $incomplete = false;

        while (! $stream->eof()) {
            // Read data in chunks
            $chunk = $stream->read(4096);
            if ($chunk === '') {
                break;
            }
            $buffer .= $chunk;

            // Process complete lines
            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);

                if (trim($line) === '') {
                    continue;
                }

                // Check if we're in the middle of a tool call
                if (strpos($line, '"tool_calls"') !== false) {
                    $incomplete = true;
                }

                // If we have a complete tool call, mark it as complete
                if ($incomplete && strpos($line, '</tool_call>') !== false) {
                    $incomplete = false;
                }

                yield $line;
            }
        }

        // Yield any remaining data in the buffer
        if (trim($buffer) !== '') {
            yield $buffer;
        }
    }

    /**
     * Create embeddings for given text
     *
     * @throws GuzzleException
     */
    public function createEmbeddings(string $model, string|array $input): array
    {
        $response = $this->client->post('v1/embeddings', [
            'json' => [
                'model' => $model,
                'input' => $input,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get the HTTP client instance
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Get the configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
