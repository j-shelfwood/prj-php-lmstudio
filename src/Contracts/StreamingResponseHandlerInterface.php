<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Contracts;

use Generator;
use Psr\Http\Message\ResponseInterface;
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Tool\ToolCall;

interface StreamingResponseHandlerInterface
{
    /**
     * Handle the streaming response and yield messages or tool calls
     *
     * @return Generator<Message|ToolCall>
     */
    public function handle(ResponseInterface $response): Generator;
}
