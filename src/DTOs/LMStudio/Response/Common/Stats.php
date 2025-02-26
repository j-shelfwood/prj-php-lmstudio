<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\LMStudio\Response\Common;

use JsonSerializable;

final readonly class Stats implements JsonSerializable
{
    public function __construct(
        public ?float $tokensPerSecond = null,
        public ?float $timeToFirstToken = null,
        public ?float $generationTime = null,
        public ?string $stopReason = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tokensPerSecond: $data['tokens_per_second'] ?? null,
            timeToFirstToken: $data['time_to_first_token'] ?? null,
            generationTime: $data['generation_time'] ?? null,
            stopReason: $data['stop_reason'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'tokens_per_second' => $this->tokensPerSecond,
            'time_to_first_token' => $this->timeToFirstToken,
            'generation_time' => $this->generationTime,
            'stop_reason' => $this->stopReason,
        ], fn ($value) => $value !== null);
    }
}
