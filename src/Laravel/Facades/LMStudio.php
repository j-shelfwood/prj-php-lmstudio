<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Shelfwood\LMStudio\Api\Client\ApiClient createApiClient()
 * @method static \Shelfwood\LMStudio\Api\Service\ModelService createModelService()
 * @method static \Shelfwood\LMStudio\Api\Service\ChatService createChatService()
 * @method static \Shelfwood\LMStudio\Api\Service\CompletionService createCompletionService()
 * @method static \Shelfwood\LMStudio\Api\Service\EmbeddingService createEmbeddingService()
 * @method static \Shelfwood\LMStudio\Core\Conversation\Conversation createConversation(string $model, array $options = [], ?\Shelfwood\LMStudio\Core\Tool\ToolRegistry $toolRegistry = null, ?\Shelfwood\LMStudio\Core\Event\EventHandler $eventHandler = null, bool $streaming = false)
 * @method static \Shelfwood\LMStudio\Core\Builder\ConversationBuilder createConversationBuilder(string $model)
 * @method static \Shelfwood\LMStudio\Laravel\Conversation\QueueableConversationBuilder createQueueableConversationBuilder(string $model)
 *
 * @see \Shelfwood\LMStudio\LMStudioFactory
 */
class LMStudio extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'lmstudio';
    }
}
