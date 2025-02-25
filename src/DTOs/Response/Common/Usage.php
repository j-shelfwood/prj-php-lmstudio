<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Response\Common;

use JsonSerializable;

final readonly class Usage implements JsonSerializable
{
    public function __construct(
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public int $totalTokens = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            promptTokens: $data['prompt_tokens'] ?? 0,
            completionTokens: $data['completion_tokens'] ?? 0,
            totalTokens: $data['total_tokens'] ?? 0,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }
}
