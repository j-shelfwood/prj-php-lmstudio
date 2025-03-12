<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Enum;

enum ModelState: string
{
    case LOADED = 'loaded';
    case NOT_LOADED = 'not-loaded';
}