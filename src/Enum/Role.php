<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Enum;

enum Role: string
{
    case SYSTEM = 'system';
    case USER = 'user';
    case ASSISTANT = 'assistant';
    case TOOL = 'tool';
}
