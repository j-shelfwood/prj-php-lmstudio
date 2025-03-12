<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http\Factories;

use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Http\Requests\Common\RequestInterface;
use Shelfwood\LMStudio\Http\Requests\V0\ChatCompletionRequest;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;

/**
 * Factory for creating request objects.
 */
class RequestFactory implements RequestFactoryInterface
{
    /**
     * Create a new request factory.
     */
    public function __construct(
        protected LMStudioConfig $config
    ) {}

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
    ): RequestInterface {
        $request = new ChatCompletionRequest(
            $chatHistory,
            $model ?? $this->config->getDefaultModel() ?? 'gpt-3.5-turbo'
        );

        if ($temperature !== null) {
            $request = $request->withTemperature($temperature);
        }

        if ($maxTokens !== null) {
            $request = $request->withMaxTokens($maxTokens);
        }

        if (! empty($tools)) {
            $request = $request->withTools($tools);
            $request = $request->withToolChoice($toolUseMode);
        }

        if ($streaming) {
            $request = $request->withStreaming(true);
        }

        return $request;
    }
}
