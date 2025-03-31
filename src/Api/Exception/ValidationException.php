<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Exception;

class ValidationException extends LMStudioException
{
    /** @var array<string, mixed> */
    protected array $errors;

    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(string $message, array $errors = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
