<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Response\Common;

use JsonSerializable;

final readonly class Choice implements JsonSerializable
{
    public function __construct(
        public int $index,
        public ?string $finishReason,
        public ?Message $message = null,
        public ?array $logprobs = null,
        public ?string $text = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            index: $data['index'] ?? 0,
            finishReason: $data['finish_reason'] ?? null,
            message: isset($data['message']) ? Message::fromArray($data['message']) : null,
            logprobs: $data['logprobs'] ?? null,
            text: $data['text'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'index' => $this->index,
            'finish_reason' => $this->finishReason,
            'message' => $this->message?->jsonSerialize(),
            'logprobs' => $this->logprobs,
            'text' => $this->text,
        ], fn ($value) => $value !== null);
    }
}
