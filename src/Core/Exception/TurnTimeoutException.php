<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Exception;

class TurnTimeoutException extends \RuntimeException
{
    public function __construct(string $message = 'The conversation turn timed out.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
