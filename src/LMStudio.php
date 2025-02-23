<?php

namespace Shelfwood\LMStudio;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shelfwood\LMStudio\Exceptions\ConnectionException;
use Shelfwood\LMStudio\Exceptions\ValidationException;
use Shelfwood\LMStudio\Support\ChatBuilder;

class LMStudio
{
    private Client $client;

    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = 1234,
        private readonly int $timeout = 30
    ) {
        $this->validateConfig();
        $this->client = new Client([
            'base_uri' => "http://{$this->host}:{$this->port}",
            'timeout' => $this->timeout,
        ]);
    }

    private function validateConfig(): void
    {
        if (empty($this->host)) {
            throw ValidationException::invalidConfig('Host cannot be empty');
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw ValidationException::invalidConfig(
                'Port must be between 1 and 65535',
                ['port' => $this->port]
            );
        }

        if ($this->timeout < 1) {
            throw ValidationException::invalidConfig(
                'Timeout must be greater than 0',
                ['timeout' => $this->timeout]
            );
        }
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
     * @throws ConnectionException
     */
    public function listModels(): array
    {
        try {
            $response = $this->client->get('/v1/models');
            $data = json_decode($response->getBody()->getContents(), true);

            if (! is_array($data)) {
                throw ConnectionException::invalidResponse('Response is not a valid JSON array');
            }

            return $data;
        } catch (GuzzleException $e) {
            throw ConnectionException::connectionFailed($e->getMessage());
        }
    }

    /**
     * Get model information
     *
     * @throws ConnectionException
     */
    public function getModel(string $model): array
    {
        if (empty($model)) {
            throw ValidationException::invalidModel('Model identifier cannot be empty');
        }

        try {
            $response = $this->client->get("/v1/models/{$model}");
            $data = json_decode($response->getBody()->getContents(), true);

            if (! is_array($data)) {
                throw ConnectionException::invalidResponse('Response is not a valid JSON array');
            }

            return $data;
        } catch (GuzzleException $e) {
            throw ConnectionException::connectionFailed($e->getMessage());
        }
    }

    /**
     * Send a chat completion request
     *
     * @throws ConnectionException
     */
    public function createChatCompletion(array $parameters): mixed
    {
        if (empty($parameters['model'])) {
            throw ValidationException::invalidModel('Model must be specified');
        }

        if (empty($parameters['messages'])) {
            throw ValidationException::invalidMessage('At least one message is required');
        }

        try {
            $response = $this->client->post('/v1/chat/completions', [
                'json' => $parameters,
                'stream' => $parameters['stream'] ?? false,
            ]);

            if ($parameters['stream'] ?? false) {
                return $this->handleStreamingResponse($response);
            }

            $data = json_decode($response->getBody()->getContents());

            if ($data === null) {
                throw ConnectionException::invalidResponse('Response is not a valid JSON');
            }

            return $data;
        } catch (GuzzleException $e) {
            throw ConnectionException::connectionFailed($e->getMessage());
        }
    }

    /**
     * Handle streaming response from the API
     */
    protected function handleStreamingResponse($response): \Generator
    {
        $buffer = '';
        $stream = $response->getBody();

        while (! $stream->eof()) {
            $chunk = $stream->read(1024);
            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;
            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);

                if (empty(trim($line))) {
                    continue;
                }

                if (str_starts_with($line, 'data: ')) {
                    $line = substr($line, 6);
                }

                if ($line === '[DONE]') {
                    continue;
                }

                try {
                    $data = json_decode($line);
                    if (! is_object($data)) {
                        continue;
                    }

                    yield $data;
                } catch (\JsonException $e) {
                    continue;
                }
            }
        }

        if (! empty(trim($buffer))) {
            try {
                $data = json_decode($buffer);
                if (is_object($data)) {
                    yield $data;
                }
            } catch (\JsonException $e) {
                // Ignore invalid JSON at the end of the stream
            }
        }
    }

    /**
     * Create embeddings for given text
     *
     * @throws ConnectionException
     */
    public function createEmbeddings(string $model, string|array $input): array
    {
        if (empty($model)) {
            throw ValidationException::invalidModel('Model identifier cannot be empty');
        }

        if (empty($input)) {
            throw ValidationException::invalidMessage('Input cannot be empty');
        }

        try {
            $response = $this->client->post('/v1/embeddings', [
                'json' => [
                    'model' => $model,
                    'input' => $input,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (! is_array($data)) {
                throw ConnectionException::invalidResponse('Response is not a valid JSON array');
            }

            return $data;
        } catch (GuzzleException $e) {
            throw ConnectionException::connectionFailed($e->getMessage());
        }
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
        return [
            'host' => $this->host,
            'port' => $this->port,
            'timeout' => $this->timeout,
        ];
    }
}
