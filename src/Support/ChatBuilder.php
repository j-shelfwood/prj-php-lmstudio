<?php

namespace Shelfwood\LMStudio\Support;

use Closure;
use Shelfwood\LMStudio\LMStudio;

class ChatBuilder
{
    protected array $messages = [];

    protected ?string $model = null;

    protected array $tools = [];

    protected array $toolHandlers = [];

    protected float $temperature;

    protected int $maxTokens;

    protected bool $stream = false;

    protected ?Closure $streamHandler = null;

    public function __construct(protected LMStudio $client)
    {
        $this->temperature = $client->getConfig()['temperature'] ?? 0.7;
        $this->maxTokens = $client->getConfig()['max_tokens'] ?? -1;
    }

    /**
     * Set the model to use
     */
    public function withModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set the messages for the chat
     *
     * @param  array<array{role: string, content: string}>  $messages
     */
    public function withMessages(array $messages): self
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * Add a single message to the chat
     */
    public function addMessage(string $role, string $content): self
    {
        $this->messages[] = [
            'role' => $role,
            'content' => $content,
        ];

        return $this;
    }

    /**
     * Set the tools available for the chat
     */
    public function withTools(array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    /**
     * Register a tool handler
     */
    public function withToolHandler(string $name, callable $handler): self
    {
        $this->toolHandlers[$name] = $handler;

        return $this;
    }

    /**
     * Enable streaming with an optional handler
     */
    public function stream(?callable $handler = null): mixed
    {
        $this->stream = true;
        $this->streamHandler = $handler ? Closure::fromCallable($handler) : null;

        return $this->send();
    }

    /**
     * Send the chat request
     */
    public function send(): mixed
    {
        $parameters = [
            'model' => $this->model ?? $this->client->getConfig()['default_model'],
            'messages' => $this->messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'stream' => $this->stream,
        ];

        if (! empty($this->tools)) {
            $parameters['tools'] = $this->tools;
        }

        $response = $this->client->createChatCompletion($parameters);

        if ($this->stream && $this->streamHandler) {
            return $this->handleStream($response);
        }

        return $response;
    }

    /**
     * Handle streaming response
     */
    protected function handleStream($response): string
    {
        $fullContent = '';

        foreach ($response as $chunk) {
            if (isset($chunk['choices'][0]['delta']['content'])) {
                $content = $chunk['choices'][0]['delta']['content'];
                $fullContent .= $content;

                if ($this->streamHandler) {
                    ($this->streamHandler)((object) [
                        'content' => $content,
                        'isToolCall' => false,
                    ]);
                }
            }

            if (isset($chunk['choices'][0]['delta']['tool_calls'])) {
                $toolCall = $chunk['choices'][0]['delta']['tool_calls'][0];

                if ($this->streamHandler) {
                    ($this->streamHandler)((object) [
                        'content' => '',
                        'isToolCall' => true,
                        'toolCall' => $toolCall,
                    ]);
                }

                if (isset($this->toolHandlers[$toolCall['function']['name']])) {
                    $result = ($this->toolHandlers[$toolCall['function']['name']])(
                        json_decode($toolCall['function']['arguments'], true)
                    );

                    $this->messages[] = [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [$toolCall],
                    ];

                    $this->messages[] = [
                        'role' => 'tool',
                        'content' => json_encode($result),
                        'tool_call_id' => $toolCall['id'],
                    ];
                }
            }
        }

        return $fullContent;
    }
}
