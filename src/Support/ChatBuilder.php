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
        $currentToolCall = null;

        foreach ($response as $line) {
            // Skip empty lines
            if (empty(trim($line))) {
                continue;
            }

            // Remove "data: " prefix if present
            $line = preg_replace('/^data: /', '', trim($line));

            // Skip [DONE] message
            if ($line === '[DONE]') {
                continue;
            }

            try {
                $chunk = json_decode($line, true);
                if (! $chunk || ! isset($chunk['choices'][0]['delta'])) {
                    continue;
                }

                $delta = $chunk['choices'][0]['delta'];

                // Handle regular content
                if (isset($delta['content']) && $delta['content'] !== null) {
                    $content = $delta['content'];
                    $fullContent .= $content;

                    if ($this->streamHandler) {
                        ($this->streamHandler)((object) [
                            'content' => $content,
                            'isToolCall' => false,
                        ]);
                    }
                }

                // Handle tool calls
                if (isset($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $toolCallDelta) {
                        // Initialize new tool call if needed
                        if (isset($toolCallDelta['id']) && (! $currentToolCall || $toolCallDelta['id'] !== $currentToolCall['id'])) {
                            $currentToolCall = [
                                'id' => $toolCallDelta['id'],
                                'type' => 'function',
                                'function' => [
                                    'name' => '',
                                    'arguments' => '',
                                ],
                            ];
                        }

                        // Update function name if present
                        if (isset($toolCallDelta['function']['name'])) {
                            $currentToolCall['function']['name'] = $toolCallDelta['function']['name'];
                        }

                        // Append arguments if present
                        if (isset($toolCallDelta['function']['arguments'])) {
                            $currentToolCall['function']['arguments'] .= $toolCallDelta['function']['arguments'];
                        }

                        // Check if we have a complete tool call
                        if ($currentToolCall &&
                            $currentToolCall['function']['name'] &&
                            $currentToolCall['function']['arguments']) {
                            // Try to validate if we have complete JSON in arguments
                            try {
                                $args = json_decode($currentToolCall['function']['arguments'], true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    // We have a complete tool call
                                    if ($this->streamHandler) {
                                        ($this->streamHandler)((object) [
                                            'content' => '',
                                            'isToolCall' => true,
                                            'toolCall' => $currentToolCall,
                                        ]);
                                    }

                                    // Process the tool call
                                    $this->processCompletedToolCall($currentToolCall);
                                    $currentToolCall = null;
                                }
                            } catch (\JsonException $e) {
                                // Not complete JSON yet, continue accumulating
                                continue;
                            }
                        }
                    }
                }
            } catch (\JsonException $e) {
                continue;
            }
        }

        return $fullContent;
    }

    /**
     * Process a completed tool call and continue the conversation
     */
    protected function processCompletedToolCall(array $toolCall): void
    {
        if (! isset($this->toolHandlers[$toolCall['function']['name']])) {
            return;
        }

        try {
            $arguments = json_decode($toolCall['function']['arguments'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \JsonException('Invalid tool call arguments');
            }

            // Execute the tool handler
            $result = ($this->toolHandlers[$toolCall['function']['name']])($arguments);

            // Add the tool call to messages
            $this->messages[] = [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [$toolCall],
            ];

            // Add the tool response to messages
            $this->messages[] = [
                'role' => 'tool',
                'content' => json_encode($result),
                'tool_call_id' => $toolCall['id'],
            ];

            // Send a follow-up request without tools to get the final response
            $response = $this->client->createChatCompletion([
                'model' => $this->model,
                'messages' => $this->messages,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
                'stream' => true,
            ]);

            // Process the follow-up response
            foreach ($response as $line) {
                if (empty(trim($line))) {
                    continue;
                }

                $line = preg_replace('/^data: /', '', trim($line));
                if ($line === '[DONE]') {
                    continue;
                }

                try {
                    $chunk = json_decode($line, true);
                    if ($chunk && isset($chunk['choices'][0]['delta']['content'])) {
                        $content = $chunk['choices'][0]['delta']['content'];
                        if ($this->streamHandler) {
                            ($this->streamHandler)((object) [
                                'content' => $content,
                                'isToolCall' => false,
                            ]);
                        }
                    }
                } catch (\JsonException $e) {
                    continue;
                }
            }
        } catch (\Exception $e) {
            error_log('Error processing tool call: '.$e->getMessage());
        }
    }
}
