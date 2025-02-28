<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Requests\Common;

/**
 * Base class for all request objects.
 */
abstract class BaseRequest implements RequestInterface
{
    /**
     * Convert the request to an array.
     */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    /**
     * Convert the request to JSON.
     */
    public function jsonSerialize(): array
    {
        return [];
    }
}
