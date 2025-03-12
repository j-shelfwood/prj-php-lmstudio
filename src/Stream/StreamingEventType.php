<?php

namespace Shelfwood\LMStudio\Stream;

/**
 * Types of events that can be sent in a stream.
 */
enum StreamEventType: string
{
    case CHUNK = 'chunk';
    case TOOL_RESULT = 'tool_result';
    case TOOL_CALL = 'tool_call';
    case TOOL_START = 'tool_start';
    case TOOL_PROGRESS = 'tool_progress';
    case ERROR = 'error';
    case COMPLETE = 'complete';
    case CONTINUING = 'continuing';
    case STATE_CHANGE = 'state_change';
}