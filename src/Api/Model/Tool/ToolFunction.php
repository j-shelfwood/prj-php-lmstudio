<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model\Tool;

class ToolFunction
{
    private string $name;

    private string $arguments;

    public function __construct(string $name, string $arguments)
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): string
    {
        return $this->arguments;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            $data['arguments']
        );
    }
}
