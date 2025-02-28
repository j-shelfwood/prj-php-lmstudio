<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Requests\V0;

use Shelfwood\LMStudio\Requests\Common\BaseRequest;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;
use Shelfwood\LMStudio\ValueObjects\Tool;

/**
 * Request for chat completions using the LM Studio API (v0).
 */
class ChatCompletionRequest extends BaseRequest
{
    /**
     * @var array<Message>|ChatHistory The messages to generate a completion for
     */
    private array|ChatHistory $messages;

    /**
     * @var array<Tool> The tools available to the model
     */
    private array $tools = [];

    /**
     * @var string|array|null The tool choice strategy
     */
    private string|array|null $toolChoice = null;

    /**
     * @var array Additional options for the completion
     */
    private array $options = [];

    /**
     * Create a new chat completion request.
     *
     * @param  array<Message>|ChatHistory  $messages  The messages to generate a completion for
     * @param  string  $model  The model to use
     * @param  array  $options  Additional options for the completion
     */
    public function __construct(array|ChatHistory $messages, string $model, array $options = [])
    {
        $this->messages = $messages;
        $this->options = array_merge([
            'model' => $model,
            'temperature' => 0.7,
            'max_tokens' => 150,
            'stream' => false,
        ], $options);
    }

    /**
     * Set the temperature for the completion.
     */
    public function withTemperature(float $temperature): self
    {
        $clone = clone $this;
        $clone->options['temperature'] = $temperature;

        return $clone;
    }

    /**
     * Set the maximum number of tokens to generate.
     */
    public function withMaxTokens(int $maxTokens): self
    {
        $clone = clone $this;
        $clone->options['max_tokens'] = $maxTokens;

        return $clone;
    }

    /**
     * Enable streaming for the completion.
     */
    public function withStreaming(bool $stream = true): self
    {
        $clone = clone $this;
        $clone->options['stream'] = $stream;

        return $clone;
    }

    /**
     * Set the tools available to the model.
     *
     * @param  array<Tool>  $tools  The tools available to the model
     */
    public function withTools(array $tools): self
    {
        $clone = clone $this;
        $clone->tools = $tools;

        return $clone;
    }

    /**
     * Set the tool choice strategy.
     *
     * @param  string|array|null  $toolChoice  The tool choice strategy
     */
    public function withToolChoice(string|array|null $toolChoice): self
    {
        $clone = clone $this;
        $clone->toolChoice = $toolChoice;

        return $clone;
    }

    /**
     * Convert the request to an array.
     */
    public function jsonSerialize(): array
    {
        $data = $this->options;

        // Convert messages to array
        if ($this->messages instanceof ChatHistory) {
            $data['messages'] = $this->messages->jsonSerialize();
        } else {
            $data['messages'] = array_map(
                function ($message) {
                    // Handle both Message objects and arrays
                    if ($message instanceof Message) {
                        return $message->jsonSerialize();
                    }

                    // If it's already an array, return it as is
                    return $message;
                },
                $this->messages
            );
        }

        // Add tools if present
        if (! empty($this->tools)) {
            $data['tools'] = array_map(
                fn (Tool $tool) => $tool->jsonSerialize(),
                $this->tools
            );
        }

        // Add tool choice if present
        if ($this->toolChoice !== null) {
            $data['tool_choice'] = $this->toolChoice;
        }

        return $data;
    }
}
