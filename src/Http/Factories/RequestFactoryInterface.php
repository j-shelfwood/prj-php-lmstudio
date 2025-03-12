<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http\Factories;

use Shelfwood\LMStudio\Http\Requests\Common\RequestInterface;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;

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
