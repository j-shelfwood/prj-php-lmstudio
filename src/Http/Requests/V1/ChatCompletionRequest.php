<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http\Requests\V1;

use Shelfwood\LMStudio\Http\Requests\Common\BaseRequest;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\JsonSchema;
use Shelfwood\LMStudio\ValueObjects\Message;
use Shelfwood\LMStudio\ValueObjects\ResponseFormat;
use Shelfwood\LMStudio\ValueObjects\Tool;

/**
 * Request for chat completions using the OpenAI-compatible API (v1).
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
     * @var string|array<string, mixed>|null The tool choice strategy
     */
    private string|array|null $toolChoice = null;

    /**
     * @var array<string, mixed>|null The response format configuration
     */
    private ?array $responseFormat = null;

    /**
     * @var int|null The time-to-live (TTL) in seconds for the model
     */
    private ?int $ttl = null;

    /**
     * @var bool|null The just-in-time (JIT) loading flag for the model
     */
    private ?bool $jit = null;

    /**
     * @var array<string, mixed> Additional options for the completion
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
     * @param  string|array<string, mixed>|null  $toolChoice  The tool choice strategy
     */
    public function withToolChoice(string|array|null $toolChoice): self
    {
        $clone = clone $this;
        $clone->toolChoice = $toolChoice;

        return $clone;
    }

    /**
     * Set the response format to enforce structured JSON output.
     *
     * This method configures the request to enforce a structured JSON response from the model
     * according to the provided schema. The LM Studio API requires the schema to be nested
     * under a 'schema' key within the 'json_schema' object.
     *
     * Example usage:
     * ```php
     * // Using an array
     * $schema = [
     *     'type' => 'object',
     *     'properties' => [
     *         'joke' => [
     *             'type' => 'string',
     *         ],
     *     ],
     *     'required' => ['joke'],
     * ];
     *
     * $request = $request->withResponseFormat($schema, 'joke_response', true);
     *
     * // Using JsonSchema value object
     * $schema = JsonSchema::keyValue('joke', 'string', 'A funny joke', 'joke_response', true);
     * $request = $request->withResponseFormat($schema);
     * ```
     *
     * @param  JsonSchema|array<string, mixed>  $schema  The JSON schema to enforce
     * @param  string|null  $name  Optional name for the schema (ignored if $schema is a JsonSchema)
     * @param  bool|null  $strict  Whether to enforce strict schema validation (ignored if $schema is a JsonSchema)
     */
    public function withResponseFormat($schema, ?string $name = null, ?bool $strict = null): self
    {
        $clone = clone $this;

        if ($schema instanceof JsonSchema) {
            $jsonSchema = $schema;
        } else {
            $jsonSchema = new JsonSchema($schema, $name, $strict);
        }

        $clone->responseFormat = ResponseFormat::jsonSchema($jsonSchema)->jsonSerialize();

        return $clone;
    }

    /**
     * Set the TTL (time-to-live) in seconds for the model.
     * When the TTL expires, the model is automatically unloaded from memory.
     */
    public function withTtl(int $seconds): self
    {
        $clone = clone $this;
        $clone->ttl = $seconds;

        return $clone;
    }

    /**
     * Set the JIT loading flag for the model.
     *
     * @param  bool  $enabled  Whether to load the model just-in-time when needed
     */
    public function withJit(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->jit = $enabled;

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

        // Add response format if present
        if ($this->responseFormat !== null) {
            $data['response_format'] = $this->responseFormat;
        }

        // Add TTL if present
        if ($this->ttl !== null) {
            $data['ttl'] = $this->ttl;
        }

        // Add JIT if present
        if ($this->jit !== null) {
            $data['jit'] = $this->jit;
        }

        return $data;
    }
}
