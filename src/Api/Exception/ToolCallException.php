<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Exception;

class ToolCallException extends \Exception
{
    private array $context;

    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        $this->context = $context;
        parent::__construct($message, 0, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public static function missingToolCall(string $response, array $context = []): self
    {
        return new self(
            'Expected tool calls but none were made in the response',
            array_merge(['response' => $response], $context)
        );
    }

    public static function invalidToolCallFormat(array $toolCall, array $context): self
    {
        return new self(sprintf(
            "Invalid tool call format. Expected 'name' and 'arguments'. Got: %s\nContext: %s",
            json_encode($toolCall),
            json_encode($context)
        ));
    }

    public static function invalidToolCallArguments(string $arguments, string $error, array $context = []): self
    {
        return new self(
            'Invalid tool call arguments',
            array_merge([
                'arguments' => $arguments,
                'error' => $error,
            ], $context)
        );
    }

    public static function streamingToolCallError(string $error, array $chunk, array $context = []): self
    {
        return new self(
            'Error processing streaming tool call',
            array_merge([
                'error' => $error,
                'chunk' => $chunk,
            ], $context)
        );
    }

    public static function malformedToolCallContent(string $content): self
    {
        return new self(sprintf(
            'Malformed tool call content: %s',
            $content
        ));
    }
}
