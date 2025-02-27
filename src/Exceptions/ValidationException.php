<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Exceptions;

class ValidationException extends LMStudioException
{
    public static function invalidConfig(string $message, array $context = []): self
    {
        return (new self("Invalid configuration: {$message}"))
            ->withContext($context);
    }

    public static function invalidModel(string $message, array $context = []): self
    {
        return (new self("Invalid model: {$message}"))
            ->withContext($context);
    }

    public static function invalidMessage(string $message, array $context = []): self
    {
        return (new self("Invalid message: {$message}"))
            ->withContext($context);
    }

    public static function invalidTool(string $message, array $context = []): self
    {
        return (new self("Invalid tool: {$message}"))
            ->withContext($context);
    }

    public static function invalidPort(string $message): self
    {
        return new self($message);
    }

    public static function invalidTimeout(string $message): self
    {
        return new self($message);
    }

    public static function invalidTemperature(string $message): self
    {
        return new self($message);
    }

    public static function invalidRetryAttempts(string $message): self
    {
        return new self($message);
    }

    public static function invalidRetryDelay(string $message): self
    {
        return new self($message);
    }

    public static function invalidTtl(string $message): self
    {
        return new self($message);
    }

    public static function invalidToolUseMode(string $message): self
    {
        return new self($message);
    }

    public static function invalidInput(string $message, array $context = []): self
    {
        return (new self("Invalid input: {$message}"))
            ->withContext($context);
    }
}
