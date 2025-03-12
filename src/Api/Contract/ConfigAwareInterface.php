<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Contract;

use Shelfwood\LMStudio\Core\Config\LMStudioConfig;

/**
 * Interface for clients that can expose their configuration.
 */
interface ConfigAwareInterface
{
    /**
     * Get the client configuration.
     */
    public function getConfig(): LMStudioConfig;
}
