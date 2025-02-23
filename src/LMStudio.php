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
    public function createChatCompletion(array $parameters): array
    {
        $response = $this->client->post('v1/chat/completions', [
            'json' => array_merge([
                'temperature' => $this->config['temperature'] ?? 0.7,
                'max_tokens' => $this->config['max_tokens'] ?? -1,
            ], $parameters),
        ]);

        return json_decode($response->getBody()->getContents(), true);
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
