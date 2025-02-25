<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Support;

use InvalidArgumentException;
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Chat\ResponseFormat;
use Shelfwood\LMStudio\DTOs\Chat\Role;
use Shelfwood\LMStudio\LMStudio;

class StructuredOutputBuilder
{
    private LMStudio $lmstudio;

    private ?string $model = null;

    private array $messages = [];

    private ?ResponseFormat $responseFormat = null;

    private array $options = [];

    /**
     * Create a new structured output builder instance
     */
    public function __construct(LMStudio $lmstudio)
    {
        $this->lmstudio = $lmstudio;
    }

    /**
     * Set the model to use for this request
     *
     * @param  string  $model  Model identifier
     * @return $this
     */
    public function withModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Add a system message to the conversation
     *
     * @param  string  $content  Message content
     * @return $this
     */
    public function withSystemMessage(string $content): self
    {
        $this->messages[] = new Message(Role::SYSTEM, $content);

        return $this;
    }

    /**
     * Add a user message to the conversation
     *
     * @param  string  $content  Message content
     * @return $this
     */
    public function withUserMessage(string $content): self
    {
        $this->messages[] = new Message(Role::USER, $content);

        return $this;
    }

    /**
     * Define a schema for the response
     *
     * @param  string  $name  Name of the schema
     * @param  array<string, mixed>  $properties  The properties of the JSON schema
     * @param  array<int, string>  $required  Required properties
     * @param  bool  $strict  Whether to enforce strict validation
     * @return $this
     */
    public function withSchema(
        string $name,
        array $properties,
        array $required = [],
        bool $strict = true
    ): self {
        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (! empty($required)) {
            $schema['required'] = $required;
        }

        $this->responseFormat = ResponseFormat::jsonSchema($name, $schema, $strict);

        return $this;
    }

    /**
     * Add additional options like temperature, max_tokens, etc.
     *
     * @param  string  $key  Option key
     * @param  mixed  $value  Option value
     * @return $this
     */
    public function withOption(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Send the request and get structured output
     *
     * @return array<string, mixed> The structured JSON response
     *
     * @throws InvalidArgumentException
     */
    public function send(): array
    {
        if (empty($this->messages)) {
            throw new InvalidArgumentException('At least one message is required');
        }

        if ($this->responseFormat === null) {
            throw new InvalidArgumentException('A response format must be provided');
        }

        $options = array_merge($this->options, [
            'response_format' => $this->responseFormat->jsonSerialize(),
        ]);

        $response = $this->lmstudio->createChatCompletion(
            messages: $this->messages,
            model: $this->model,
            options: $options
        );

        $content = $response->choices[0]->message->content;

        // Parse and return the JSON structure
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
