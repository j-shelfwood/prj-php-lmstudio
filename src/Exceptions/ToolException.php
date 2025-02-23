<?php

namespace Shelfwood\LMStudio\Exceptions;

class ToolException extends LMStudioException
{
    public static function handlerNotFound(string $toolName, array $context = []): self
    {
        return (new self('Tool handler not found for tool: '.$toolName))
            ->withContext($context);
    }

    public static function invalidToolCall(string $message, array $context = []): self
    {
        return (new self('Invalid tool call: '.$message))
            ->withContext($context);
    }

    public static function toolExecutionFailed(string $toolName, string $message, array $context = []): self
    {
        return (new self("Tool execution failed for {$toolName}: {$message}"))
            ->withContext($context);
    }
}
