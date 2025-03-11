<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Exceptions;

/**
 * Exception thrown when an API request fails.
 */
class ApiRequestException extends LMStudioException
{
    /**
     * @var int|null The HTTP status code
     */
    private ?int $statusCode;

    /**
     * @var array<string, mixed>|null The error response body
     */
    private ?array $responseBody;

    /**
     * Create a new API request exception.
     *
     * @param  string  $message  The exception message
     * @param  int  $code  The exception code
     * @param  \Throwable|null  $previous  The previous throwable
     * @param  int|null  $statusCode  The HTTP status code
     * @param  array<string, mixed>|null  $responseBody  The error response body
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        ?int $statusCode = null,
        ?array $responseBody = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int|null The HTTP status code
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Get the error response body.
     *
     * @return array<string, mixed>|null The error response body
     */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }
}
