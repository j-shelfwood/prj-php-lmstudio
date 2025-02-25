<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Response\Common;

use JsonSerializable;

final readonly class Stats implements JsonSerializable
{
    public function __construct(
        public float $tokensPerSecond,
        public float $timeToFirstToken,
        public float $generationTime,
        public string $stopReason,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tokensPerSecond: $data['tokens_per_second'],
            timeToFirstToken: $data['time_to_first_token'],
            generationTime: $data['generation_time'],
            stopReason: $data['stop_reason'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'tokens_per_second' => $this->tokensPerSecond,
            'time_to_first_token' => $this->timeToFirstToken,
            'generation_time' => $this->generationTime,
            'stop_reason' => $this->stopReason,
        ];
    }
}
