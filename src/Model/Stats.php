<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Model;

/**
 * Represents performance statistics in a response.
 */
class Stats
{
    /**
     * @param  float  $tokensPerSecond  The tokens per second
     * @param  float  $timeToFirstToken  The time to first token in seconds
     * @param  float  $generationTime  The total generation time in seconds
     * @param  string  $stopReason  The reason for stopping
     */
    public function __construct(
        public readonly float $tokensPerSecond,
        public readonly float $timeToFirstToken,
        public readonly float $generationTime,
        public readonly string $stopReason
    ) {}

    /**
     * Create a Stats object from an array.
     *
     * @param  array|null  $data  The stats data
     * @return self|null The created object or null if data is null
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return new self(
            tokensPerSecond: (float) ($data['tokens_per_second'] ?? 0.0),
            timeToFirstToken: (float) ($data['time_to_first_token'] ?? 0.0),
            generationTime: (float) ($data['generation_time'] ?? 0.0),
            stopReason: $data['stop_reason'] ?? ''
        );
    }

    /**
     * Convert the stats to an array.
     */
    public function toArray(): array
    {
        return [
            'tokens_per_second' => $this->tokensPerSecond,
            'time_to_first_token' => $this->timeToFirstToken,
            'generation_time' => $this->generationTime,
            'stop_reason' => $this->stopReason,
        ];
    }

    /**
     * Get the tokens per second.
     */
    public function getTokensPerSecond(): float
    {
        return $this->tokensPerSecond;
    }

    /**
     * Get the time to first token in seconds.
     */
    public function getTimeToFirstToken(): float
    {
        return $this->timeToFirstToken;
    }

    /**
     * Get the total generation time in seconds.
     */
    public function getGenerationTime(): float
    {
        return $this->generationTime;
    }

    /**
     * Get the reason for stopping.
     */
    public function getStopReason(): string
    {
        return $this->stopReason;
    }
}
