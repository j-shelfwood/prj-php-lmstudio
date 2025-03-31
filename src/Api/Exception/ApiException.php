<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Exception;

class ApiException extends LMStudioException
{
    /** @var array<mixed>|null */
    protected ?array $response;

    /**
     * @param  array<mixed>|null  $response
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, ?array $response = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    /**
     * @return array<mixed>|null
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }
}
