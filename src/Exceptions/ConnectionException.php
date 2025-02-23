<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Exceptions;

class ConnectionException extends LMStudioException
{
    public static function connectionFailed(string $message, array $context = []): self
    {
        return (new self("Failed to connect to LMStudio API: {$message}"))
            ->withContext($context);
    }

    public static function invalidResponse(string $message, array $context = []): self
    {
        return (new self("Invalid response from LMStudio API: {$message}"))
            ->withContext($context);
    }
}
