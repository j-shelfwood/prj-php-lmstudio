<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http\Requests\V1;

use Shelfwood\LMStudio\Http\Requests\Common\BaseRequest;

/**
 * Request for text completions using the OpenAI-compatible API (v1).
 */
class TextCompletionRequest extends BaseRequest
{
    /**
     * @var string The prompt to generate a completion for
     */
    private string $prompt;

    /**
     * @var array<string, mixed> Additional options for the completion
     */
    private array $options = [];

    /**
     * Create a new text completion request.
     *
     * @param  string  $prompt  The prompt to generate a completion for
     * @param  string  $model  The model to use
     * @param  array  $options  Additional options for the completion
     */
    public function __construct(string $prompt, string $model, array $options = [])
    {
        $this->prompt = $prompt;
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
     * Convert the request to an array.
     */
    public function jsonSerialize(): array
    {
        return array_merge($this->options, [
            'prompt' => $this->prompt,
        ]);
    }
}
