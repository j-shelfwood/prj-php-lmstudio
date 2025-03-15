<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model\Tool;

class ToolCall
{
    private string $id;

    private string $type;

    private ToolFunction $function;

    public function __construct(string $id, string $type, ToolFunction $function)
    {
        $this->id = $id;
        $this->type = $type;
        $this->function = $function;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getFunction(): ToolFunction
    {
        return $this->function;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'function' => $this->function->toArray(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['type'],
            ToolFunction::fromArray($data['function'])
        );
    }
}
