<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Enum;

/**
 * Represents the tool choice options for API requests.
 */
enum ToolChoice: string
{
    case AUTO = 'auto';
    case NONE = 'none';
    case ANY = 'any';

    /**
     * Create a specific tool choice for a named function.
     *
     * @param  string  $functionName  The name of the function to use
     * @return array The tool choice configuration
     */
    public static function function(string $functionName): array
    {
        return [
            'type' => ToolType::FUNCTION->value,
            'function' => [
                'name' => $functionName,
            ],
        ];
    }
}
