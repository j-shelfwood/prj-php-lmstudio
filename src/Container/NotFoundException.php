<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Container;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Exception thrown when a service is not found in the container.
 */
class NotFoundException extends \Exception implements NotFoundExceptionInterface {}
