<?php

declare(strict_types=1);

namespace Shelfwood\Lmstudio\Core\Tool\Exception;

/**
 * Base exception for errors occurring during tool execution.
 */
class ToolExecutionException extends \RuntimeException
{
    protected mixed $details;

    public function __construct(string $message = '', mixed $details = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->details = $details;
    }

    public function getDetails(): mixed
    {
        return $this->details;
    }
}
