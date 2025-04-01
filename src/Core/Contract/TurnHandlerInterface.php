<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Contract;

use Shelfwood\LMStudio\Core\Conversation\ConversationState; // Updated use statement

/**
 * Defines the contract for handling a single turn within a conversation.
 */
interface TurnHandlerInterface
{
    /**
     * Handle a conversation turn.
     *
     * This method encapsulates the logic for interacting with the AI model,
     * potentially handling tool calls, managing streaming (if applicable),
     * and updating the conversation state.
     *
     * @param  ConversationState  $state  The current state of the conversation. Implementations may modify this state.
     * @param  int|null  $timeout  Optional timeout in seconds for the turn.
     * @return string The final textual response from the assistant for this turn.
     *
     * @throws \Throwable If an error occurs during the turn (e.g., API error, tool execution error, timeout).
     */
    public function handle(ConversationState $state, ?int $timeout = null): string;
}
