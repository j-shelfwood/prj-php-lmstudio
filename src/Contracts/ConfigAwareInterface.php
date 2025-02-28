<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Contracts;

use Shelfwood\LMStudio\Config\LMStudioConfig;

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
