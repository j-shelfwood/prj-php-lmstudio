<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Common\Chat;

enum Role: string
{
    case SYSTEM = 'system';
    case USER = 'user';
    case ASSISTANT = 'assistant';
    case TOOL = 'tool';
    case FUNCTION = 'function';

    public static function isValid(string $role): bool
    {
        return in_array($role, array_column(self::cases(), 'value'), true);
    }
}
