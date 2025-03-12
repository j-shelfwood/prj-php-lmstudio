<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Exception;

class StreamingException extends LMStudioException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $lastChunk = null,
        public readonly ?int $chunkCount = null,
        public readonly ?float $elapsedTime = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
