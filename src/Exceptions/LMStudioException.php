<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Exceptions;

use RuntimeException;

class LMStudioException extends RuntimeException
{
    protected array $context = [];

    public function withContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
