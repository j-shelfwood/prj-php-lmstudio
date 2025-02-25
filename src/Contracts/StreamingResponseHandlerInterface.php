<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Contracts;

use Generator;
use Psr\Http\Message\ResponseInterface;
use Shelfwood\LMStudio\DTOs\Response\StreamingResponse;

interface StreamingResponseHandlerInterface
{
    /**
     * Handle the streaming response and yield StreamingResponse objects
     *
     * @return Generator<int, StreamingResponse, mixed, void>
     */
    public function handle(ResponseInterface $response): Generator;
}
