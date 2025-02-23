<?php

namespace Shelfwood\LMStudio\Support;

use Closure;
use Shelfwood\LMStudio\Exceptions\ToolException;
use Shelfwood\LMStudio\Exceptions\ValidationException;
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
     *
     * @throws ValidationException
     */
    public function withModel(string $model): self
    {
        if (empty($model)) {
            throw ValidationException::invalidModel('Model identifier cannot be empty');
        }

        $this->model = $model;

        return $this;
    }

    /**
     * Set the messages for the chat
     *
     * @param  array<array{role: string, content: string}>  $messages
     *
     * @throws ValidationException
     */
    public function withMessages(array $messages): self
    {
        foreach ($messages as $message) {
            if (! isset($message['role'], $message['content'])) {
                throw ValidationException::invalidMessage('Each message must have a role and content');
            }

            if (! in_array($message['role'], ['system', 'user', 'assistant', 'tool'])) {
                throw ValidationException::invalidMessage(
                    'Invalid message role: '.$message['role'],
                    ['role' => $message['role']]
                );
            }
        }

        $this->messages = $messages;

        return $this;
    }

    /**
     * Add a single message to the chat
     *
     * @throws ValidationException
     */
    public function addMessage(string $role, string $content): self
    {
        if (! isset($role, $role)) {
            throw ValidationException::invalidMessage('Each message must have a role and content');
        }

        if (! in_array($role, ['system', 'user', 'assistant', 'tool'])) {
            throw ValidationException::invalidMessage(
                'Invalid message role: '.$role,
                ['role' => $role]
            );
        }

        if (empty(trim($content))) {
            throw ValidationException::invalidMessage('Message content cannot be empty');
        }

        $this->messages[] = [
            'role' => $role,
            'content' => $content,
        ];

        return $this;
    }

    /**
     * Set the tools available for the chat
     *
     * @throws ValidationException
     */
    public function withTools(array $tools): self
    {
        foreach ($tools as $tool) {
            if (! isset($tool['type'], $tool['function'])) {
                throw ValidationException::invalidTool('Each tool must have a type and function definition');
            }

            if ($tool['type'] !== 'function') {
                throw ValidationException::invalidTool(
                    'Only function type tools are supported',
                    ['type' => $tool['type']]
                );
            }

            if (! isset($tool['function']['name'], $tool['function']['parameters'])) {
                throw ValidationException::invalidTool('Tool function must have a name and parameters');
            }
        }

        $this->tools = $tools;

        return $this;
    }

    /**
     * Register a tool handler
     *
     * @throws ValidationException
     */
    public function withToolHandler(string $name, callable $handler): self
    {
        if (empty(trim($name))) {
            throw ValidationException::invalidTool('Tool handler name cannot be empty');
        }

        $this->toolHandlers[$name] = $handler;

        return $this;
    }

    /**
     * Enable streaming
     */
    public function stream(): self
    {
        $this->stream = true;

        return $this;
    }

    /**
     * Send the chat request
     *
     * @throws ValidationException
     */
    public function send(): mixed
    {
        if ($this->model === null) {
            throw ValidationException::invalidModel('Model must be specified');
        }

        if (empty($this->messages)) {
            throw ValidationException::invalidMessage('At least one message is required');
        }

        $parameters = [
            'model' => $this->model,
            'messages' => $this->messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'stream' => $this->stream,
        ];

        if (! empty($this->tools)) {
            $parameters['tools'] = $this->tools;
        }

        $response = $this->client->createChatCompletion($parameters);

        if ($this->stream) {
            return $this->handleStream($response);
        }

        return $response;
    }

    /**
     * Handle streaming response
     */
    protected function handleStream($response): \Generator
    {
        $currentToolCall = null;

        foreach ($response as $data) {
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

                // Check if we have a complete tool call
                if ($currentToolCall['function']['name'] && $currentToolCall['function']['arguments']) {
                    try {
                        // Try to parse the arguments as JSON
                        $args = json_decode($currentToolCall['function']['arguments'], true, 512, JSON_THROW_ON_ERROR);

                        // If we got here, we have valid JSON
                        if (isset($this->toolHandlers[$currentToolCall['function']['name']])) {
                            $result = ($this->toolHandlers[$currentToolCall['function']['name']])($args);
                            yield json_encode($result, JSON_THROW_ON_ERROR);
                        } else {
                            throw new ToolException("No handler registered for tool: {$currentToolCall['function']['name']}");
                        }
                        $currentToolCall = null;
                    } catch (\JsonException $e) {
                        // Continue accumulating arguments if JSON is incomplete
                        continue;
                    }
                }
            } elseif (isset($data->choices[0]->delta->content)) {
                yield $data->choices[0]->delta->content;
            }
        }
    }

    /**
     * Process a completed tool call
     *
     * @throws ToolException
     */
    protected function processCompletedToolCall(array $toolCall): string
    {
        $name = $toolCall['function']['name'];
        $arguments = $toolCall['function']['arguments'];

        if (! isset($this->toolHandlers[$name])) {
            throw new ToolException("No handler registered for tool: {$name}");
        }

        try {
            $args = json_decode($arguments, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ToolException('Invalid tool call: Tool call arguments must be a valid JSON object');
        }

        $result = ($this->toolHandlers[$name])($args);

        return json_encode($result, JSON_THROW_ON_ERROR);
    }
}
