<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Support;

use Shelfwood\LMStudio\DTOs\Common\Chat\Message;
use Shelfwood\LMStudio\DTOs\Common\Chat\Role;
use Shelfwood\LMStudio\DTOs\Common\Tool\ToolCall;
use Shelfwood\LMStudio\DTOs\Common\Tool\ToolFunction;
use Shelfwood\LMStudio\Endpoints\APIGate;
use Shelfwood\LMStudio\Exceptions\ToolException;
use Shelfwood\LMStudio\Exceptions\ValidationException;

class ChatBuilder
{
    /** @var Message[] */
    protected array $messages = [];

    protected ?string $model = null;

    /** @var ToolCall[] */
    protected array $tools = [];

    /** @var array<string, callable(array<string, mixed>): mixed> */
    protected array $toolHandlers = [];

    public function __construct(protected APIGate $api) {}

    /**
     * Set the model to use
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
     * @param  array<Message|array>  $messages
     */
    public function withMessages(array $messages): self
    {
        $this->messages = array_map(function ($message) {
            return $message instanceof Message
                ? $message
                : Message::fromArray($message);
        }, $messages);

        return $this;
    }

    /**
     * Add a single message to the chat
     */
    public function addMessage(Role $role, string $content): self
    {
        if (empty(trim($content))) {
            throw ValidationException::invalidMessage('Message content cannot be empty');
        }

        $this->messages[] = new Message($role, $content);

        return $this;
    }

    /**
     * Set the tools available for the chat
     *
     * @param  array<ToolFunction|ToolCall|array<string, mixed>>  $tools
     */
    public function withTools(array $tools): self
    {
        $this->tools = array_map(function ($tool) {
            if ($tool instanceof ToolFunction) {
                return new ToolCall(uniqid('call_'), 'function', $tool);
            }

            if ($tool instanceof ToolCall) {
                return $tool;
            }

            if (is_array($tool)) {
                return ToolCall::fromArray($tool);
            }

            throw new ValidationException('Invalid tool type provided');
        }, $tools);

        return $this;
    }

    /**
     * Register a tool handler
     *
     * @param  callable(array<string, mixed>): mixed  $handler
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
     * Validate the chat request
     *
     * @throws ValidationException
     */
    protected function validate(): void
    {
        if ($this->model === null) {
            throw ValidationException::invalidModel('Model must be specified');
        }

        if (empty($this->messages)) {
            throw ValidationException::invalidMessage('At least one message is required');
        }
    }

    protected function getOptions(): array
    {
        $options = [];

        if (! empty($this->tools)) {
            $options['tools'] = $this->tools;
        }

        return $options;
    }

    /**
     * Send the chat request
     *
     * @throws ValidationException
     */
    public function send(): mixed
    {
        $this->validate();

        return $this->api->createChatCompletion(
            $this->messages,
            $this->model,
            $this->getOptions()
        );
    }

    /**
     * Stream the chat request
     */
    public function stream(): \Generator
    {
        $this->validate();

        return $this->api->createChatCompletionStream(
            $this->messages,
            $this->model,
            $this->getOptions()
        );
    }

    /**
     * Handle streaming response
     *
     * @param  \Iterator<object>  $response
     * @return \Generator<Message|ToolCall>
     */
    protected function handleStream(\Iterator $response): \Generator
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
                        $toolCall = ToolCall::fromArray($currentToolCall);
                        $result = $this->processToolCall($toolCall);

                        yield Message::fromArray([
                            'role' => Role::TOOL,
                            'content' => $result,
                            'name' => $toolCall->function->name,
                        ]);
                        $currentToolCall = null;
                    } catch (\JsonException $e) {
                        // Continue accumulating arguments if JSON is incomplete
                        continue;
                    }
                }
            } elseif (isset($data->choices[0]->delta->content)) {
                yield Message::fromArray([
                    'role' => Role::ASSISTANT,
                    'content' => $data->choices[0]->delta->content,
                ]);
            }
        }
    }

    /**
     * Process a tool call and return the result
     *
     * @throws ToolException|\JsonException
     */
    protected function processToolCall(ToolCall $toolCall): string
    {
        if (! isset($this->toolHandlers[$toolCall->function->name])) {
            throw new ToolException("No handler registered for tool: {$toolCall->function->name}");
        }

        if ($toolCall->arguments === null) {
            throw new ToolException("No arguments provided for tool: {$toolCall->function->name}");
        }

        $args = $toolCall->function->validateArguments($toolCall->arguments);
        $result = ($this->toolHandlers[$toolCall->function->name])($args);

        return json_encode($result, JSON_THROW_ON_ERROR);
    }
}
