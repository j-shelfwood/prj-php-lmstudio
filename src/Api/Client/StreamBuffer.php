<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Client;

class StreamBuffer
{
    private string $buffer = '';

    private int $position = 0;

    public function append(string $data): void
    {
        $this->buffer .= $data;
    }

    public function readLine(): ?string
    {
        $eolPos = strpos($this->buffer, "\n", $this->position);

        if ($eolPos === false) {
            return null;
        }

        $line = substr($this->buffer, $this->position, $eolPos - $this->position);
        $this->position = $eolPos + 1;

        return rtrim($line, "\r");
    }

    public function clear(): void
    {
        if ($this->position > 0) {
            $this->buffer = substr($this->buffer, $this->position);
            $this->position = 0;
        }
    }

    public function isEmpty(): bool
    {
        return strlen($this->buffer) <= $this->position;
    }
}
