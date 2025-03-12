<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Enum;

enum FinishReason: string
{
    case STOP = 'stop';
    case LENGTH = 'length';
    case TOOL_CALLS = 'tool_calls';
    case CONTENT_FILTER = 'content_filter';
}
