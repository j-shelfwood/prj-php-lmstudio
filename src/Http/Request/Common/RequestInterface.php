<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http\Request\Common;

/**
 * Interface for all request objects.
 */
interface RequestInterface extends \JsonSerializable
{
    /**
     * Convert the request to an array.
     */
    public function toArray(): array;
}
