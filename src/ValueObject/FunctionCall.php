<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObject;

use Shelfwood\LMStudio\Exception\InvalidToolDefinitionException;

/**
 * Represents a function call in a tool call.
 *
 * A function call specifies the name of the function to call and the arguments
 * to pass to it. The arguments are provided as a JSON string.
 *
 * Example usage:
 * ```php
 * $functionCall = new FunctionCall(
 *     'get_weather',
 *     json_encode(['location' => 'New York'])
 * );
 * ```
 */
class FunctionCall implements \JsonSerializable
{
    /**
     * Create a new function call.
     *
     * @param  string  $name  The name of the function to call
     * @param  string  $arguments  The arguments to pass to the function (JSON string)
     * @param  bool  $skipValidation  Whether to skip JSON validation (useful for testing)
     *
     * @throws InvalidToolDefinitionException If the function call is invalid
     */
    public function __construct(
        public readonly string $name,
        public readonly string $arguments,
        bool $skipValidation = false
    ) {
        if (empty($name)) {
            throw new InvalidToolDefinitionException('Function name cannot be empty');
        }

        // Validate that arguments is a valid JSON string
        if (! $skipValidation && ! empty($arguments)) {
            $decoded = json_decode($arguments, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidToolDefinitionException('Arguments must be a valid JSON string: '.json_last_error_msg());
            }
        }
    }

    /**
     * Convert the function call to an array for JSON serialization.
     *
     * @return array<string, string> The JSON-serializable array
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }

    /**
     * Get the arguments as an array.
     *
     * @return array<string, mixed> The arguments as an associative array
     */
    public function getArgumentsAsArray(): array
    {
        return json_decode($this->arguments, true) ?? [];
    }

    /**
     * Create a new function call with different arguments.
     *
     * @param  array<string, mixed>  $arguments  The new arguments
     * @param  bool  $skipValidation  Whether to skip JSON validation
     * @return self A new function call with the same name but different arguments
     *
     * @throws InvalidToolDefinitionException If JSON encoding fails
     */
    public function withArguments(array $arguments, bool $skipValidation = false): self
    {
        $jsonArguments = json_encode($arguments);

        if ($jsonArguments === false && ! $skipValidation) {
            throw new InvalidToolDefinitionException('Failed to encode arguments to JSON: '.json_last_error_msg());
        }

        return new self($this->name, $jsonArguments ?: '{}', $skipValidation);
    }
}
