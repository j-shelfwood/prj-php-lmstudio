<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Tools;

use Shelfwood\LMStudio\Api\Model\Message;

interface ConversationInterfaceForExecutor
{
    /**
     * Add a tool message to the conversation.
     *
     * @param  Message  $message  The tool message to add.
     */
    public function addMessage(Message $message): void;
}
