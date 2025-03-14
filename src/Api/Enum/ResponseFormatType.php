<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Enum;

/**
 * Represents the type of a response format.
 */
enum ResponseFormatType: string
{
    case TEXT = 'text';
    case JSON_SCHEMA = 'json_schema';
}
