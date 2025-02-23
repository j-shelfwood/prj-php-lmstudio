<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\DTOs\Model\ModelInfo;
use Shelfwood\LMStudio\DTOs\Model\ModelList;
use Shelfwood\LMStudio\DTOs\Tool\ToolCall;
use Shelfwood\LMStudio\Exceptions\ConnectionException;
use Shelfwood\LMStudio\Exceptions\ValidationException;
use Shelfwood\LMStudio\Support\ChatBuilder;

class LMStudio
{
    private Client $client;

    private readonly Config $config;

    public function __construct(
        string|Config $config = 'localhost',
        ?int $port = null,
        ?int $timeout = null
    ) {
        $this->config = is_string($config)
            ? new Config($config, $port ?? 1234, $timeout ?? 30)
            : $config;

        $this->client = new Client([
            'base_uri' => "http://{$this->config->host}:{$this->config->port}",
            'timeout' => $this->config->timeout,
        ]);
    }

    public function chat(): ChatBuilder
    {
        return new ChatBuilder($this);
    }

    /**
     * List all available models
     *
     * @throws ConnectionException
     */
    public function listModels(): ModelList
    {
        try {
            $response = $this->client->get('/v1/models');
            $data = json_decode($response->getBody()->getContents(), true);

            if (! is_array($data)) {
                throw ConnectionException::invalidResponse('Response is not a valid JSON array');
            }

            return ModelList::fromArray($data);
        } catch (GuzzleException $e) {
            throw ConnectionException::connectionFailed($e->getMessage());
        }
    }

    /**
     * Get model information
     *
     * @throws ConnectionException
     */
    public function getModel(string $model): ModelInfo
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

            return ModelInfo::fromArray($data);
        } catch (GuzzleException $e) {
            throw ConnectionException::connectionFailed($e->getMessage());
        }
    }

    /**
     * Send a chat completion request
     *
     * @param  array<Message>  $messages
     * @param  array<ToolCall>  $tools
     *
     * @throws ConnectionException
     */
    public function createChatCompletion(array $messages, ?string $model = null, array $tools = [], bool $stream = false): mixed
    {
        $model = $model ?? $this->config->defaultModel;

        if (empty($model)) {
            throw ValidationException::invalidModel('Model must be specified');
        }

        if (empty($messages)) {
            throw ValidationException::invalidMessage('At least one message is required');
        }

        $parameters = [
            'model' => $model,
            'messages' => array_map(fn (Message $message) => $message->jsonSerialize(), $messages),
            'temperature' => $this->config->temperature,
            'max_tokens' => $this->config->maxTokens,
            'stream' => $stream,
        ];

        if (! empty($tools)) {
            $parameters['tools'] = array_map(fn (ToolCall $tool) => $tool->jsonSerialize(), $tools);
        }

        try {
            $response = $this->client->post('/v1/chat/completions', [
                'json' => $parameters,
            ]);

            if ($stream) {
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
        $currentToolCall = null;

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

                    if (isset($data->choices[0]->delta->tool_calls)) {
                        $toolCallDelta = $data->choices[0]->delta->tool_calls[0];

                        if (! isset($currentToolCall)) {
                            $currentToolCall = [
                                'id' => $toolCallDelta->id ?? null,
                                'type' => $toolCallDelta->type ?? 'function',
                                'function' => [
                                    'name' => $toolCallDelta->function->name ?? '',
                                    'arguments' => '',
                                ],
                            ];
                        }

                        if (isset($toolCallDelta->function->name)) {
                            $currentToolCall['function']['name'] = $toolCallDelta->function->name;
                        }

                        if (isset($toolCallDelta->function->arguments)) {
                            $currentToolCall['function']['arguments'] .= $toolCallDelta->function->arguments;
                        }

                        if ($currentToolCall['function']['name'] && $currentToolCall['function']['arguments']) {
                            yield ToolCall::fromArray($currentToolCall);
                            $currentToolCall = null;
                        }
                    } elseif (isset($data->choices[0]->delta->content)) {
                        yield Message::fromArray([
                            'role' => 'assistant',
                            'content' => $data->choices[0]->delta->content,
                        ]);
                    }
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

    public function getConfig(): Config
    {
        return $this->config;
    }
}
