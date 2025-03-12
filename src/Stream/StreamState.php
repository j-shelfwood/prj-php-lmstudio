<?php

namespace Shelfwood\LMStudio\Stream;

/**
 * Represents the current state of a streaming response.
 */
enum StreamState: string
{
    case STARTING = 'starting';
    case STREAMING = 'streaming';
    case PROCESSING_TOOL_CALLS = 'processing_tool_calls';
    case CONTINUING = 'continuing';
    case COMPLETED = 'completed';
    case ERROR = 'error';

    /**
     * Check if this is a terminal state
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::ERROR]);
    }
}