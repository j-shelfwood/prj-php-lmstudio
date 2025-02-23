<?php

namespace Shelfwood\LMStudio\Exceptions;

class ValidationException extends LMStudioException
{
    public static function invalidConfig(string $message, array $context = []): self
    {
        return (new self('Invalid configuration: '.$message))
            ->withContext($context);
    }

    public static function invalidModel(string $message, array $context = []): self
    {
        return (new self('Invalid model: '.$message))
            ->withContext($context);
    }

    public static function invalidMessage(string $message, array $context = []): self
    {
        return (new self('Invalid message: '.$message))
            ->withContext($context);
    }

    public static function invalidTool(string $message, array $context = []): self
    {
        return (new self('Invalid tool: '.$message))
            ->withContext($context);
    }
}
