<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Service;

use Shelfwood\LMStudio\Api\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Exception\ValidationException;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\ResponseFormat;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCallFormatter;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Response\ChatCompletionResponse;

class ChatService extends AbstractService
{
    private ApiClientInterface $client;

    private ToolCallFormatter $formatter;

    public function __construct(ApiClientInterface $client)
    {
        $this->client = $client;
        $this->formatter = new ToolCallFormatter;
    }

    /**
     * Format a system message with tool definitions.
     */
    public function formatSystemMessageWithTools(string $baseMessage, array $tools): Message
    {
        $systemPrompt = $this->formatter->formatSystemPrompt($tools);

        return new Message(Role::SYSTEM, $systemPrompt);
    }

    /**
     * Parse tool calls from content.
     */
    public function parseToolCalls(string $content): array
    {
        return $this->formatter->parseToolCalls($content);
    }

    /**
     * Format a tool call.
     */
    public function formatToolCall(string $name, array $arguments): string
    {
        return $this->formatter->formatToolCall($name, $arguments);
    }

    /**
     * Create a chat completion.
     *
     * @param  array|Message[]  $messages
     * @param  array|Tool[]|ToolDefinition[]|null  $tools
     *
     * @throws ValidationException
     */
    public function createCompletion(
        string $model,
        array $messages,
        ?array $tools = null,
        ?ResponseFormat $responseFormat = null,
        ?array $options = null
    ): ChatCompletionResponse {
        $this->validateModel($model);
        $this->validateMessages($messages);

        $data = [
            'model' => $model,
            'messages' => $this->formatMessages($messages),
        ];

        if ($tools !== null) {
            $toolsArray = [];

            foreach ($tools as $tool) {
                if ($tool instanceof Tool) {
                    $toolsArray[] = $tool->toArray();
                } elseif ($tool instanceof ToolDefinition) {
                    $toolsArray[] = [
                        'type' => 'function',
                        'function' => [
                            'name' => $tool->getName(),
                            'description' => $tool->getDescription(),
                            'parameters' => $tool->getParameters()->toArray(),
                        ],
                    ];
                }
            }
            $data['tools'] = $toolsArray;
        }

        if ($responseFormat !== null) {
            $data['response_format'] = $responseFormat->toArray();
        }

        if ($options !== null) {
            $data = array_merge($data, $options);
        }

        $response = $this->client->post('/api/v0/chat/completions', $data);

        return ChatCompletionResponse::fromArray($response);
    }

    /**
     * Create a streaming chat completion.
     *
     * @param  string  $model  The model to use
     * @param  Message[]  $messages  The messages to send
     * @param  callable  $callback  The callback to handle streaming data
     * @param  Tool[]|null  $tools  The tools to use
     * @param  ResponseFormat|null  $responseFormat  The response format
     * @param  array|null  $options  Additional options
     */
    public function createCompletionStream(
        string $model,
        array $messages,
        callable $callback,
        ?array $tools = null,
        ?ResponseFormat $responseFormat = null,
        ?array $options = null
    ): void {
        $this->validateModel($model);
        $this->validateMessages($messages);
        $this->validateCallback($callback);

        $data = [
            'model' => $model,
            'messages' => $this->formatMessages($messages),
            'stream' => true,
        ];

        if ($tools !== null) {
            $toolsArray = [];

            foreach ($tools as $tool) {
                if ($tool instanceof Tool) {
                    $toolsArray[] = $tool->toArray();
                } elseif ($tool instanceof ToolDefinition) {
                    $toolsArray[] = [
                        'type' => 'function',
                        'function' => [
                            'name' => $tool->getName(),
                            'description' => $tool->getDescription(),
                            'parameters' => $tool->getParameters()->toArray(),
                        ],
                    ];
                }
            }
            $data['tools'] = $toolsArray;
        }

        if ($responseFormat !== null) {
            $data['response_format'] = $responseFormat->toArray();
        }

        if ($options !== null) {
            $data = array_merge($data, $options);
        }

        // Ensure stream is always true
        $data['stream'] = true;

        $this->client->postStream('/api/v0/chat/completions', $data, $callback);
    }

    /**
     * Validate the model.
     *
     * @param  string  $model  The model to validate
     *
     * @throws ValidationException If the model is invalid
     */
    private function validateModel(string $model): void
    {
        if (empty($model)) {
            throw new ValidationException('Model is required');
        }
    }

    /**
     * Validate the messages.
     *
     * @param  Message[]  $messages  The messages to validate
     *
     * @throws ValidationException If the messages are invalid
     */
    private function validateMessages(array $messages): void
    {
        if (empty($messages)) {
            throw new ValidationException('Messages are required');
        }

        foreach ($messages as $message) {
            if (! $message instanceof Message) {
                throw new ValidationException('Messages must be instances of Message');
            }
        }
    }

    /**
     * Validate the callback.
     *
     * @throws ValidationException
     */
    private function validateCallback(?callable $callback): void
    {
        if ($callback === null) {
            throw new ValidationException('Callback is required for streaming');
        }
    }

    /**
     * Format messages for API request.
     *
     * @param  array|Message[]  $messages
     */
    private function formatMessages(array $messages): array
    {
        return array_map(fn (Message $message) => $message->toArray(), $messages);
    }
}
