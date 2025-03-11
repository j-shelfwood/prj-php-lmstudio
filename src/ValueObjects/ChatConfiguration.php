<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\ValueObjects;

class ChatConfiguration implements \JsonSerializable
{
    public function __construct(
        private string $model,
        private float $temperature,
        private int $maxTokens,
        private bool $streaming,
        private bool $tools,
        private string $systemMessage,
        private array $metadata,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'model' => $this->model,
            'temperature' => $this->temperature,
        ];
    }
}
