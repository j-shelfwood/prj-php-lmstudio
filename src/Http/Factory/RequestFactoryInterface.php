<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http\Factory;

use Shelfwood\LMStudio\Http\Request\Common\RequestInterface;
use Shelfwood\LMStudio\ValueObject\ChatHistory;

/**
 * Interface for request factories.
 */
interface RequestFactoryInterface
{
    /**
     * Create a chat completion request.
     */
    public function createChatCompletionRequest(
        ChatHistory $chatHistory,
        ?string $model = null,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $tools = [],
        string $toolUseMode = 'auto',
        bool $streaming = false
    ): RequestInterface;
}
