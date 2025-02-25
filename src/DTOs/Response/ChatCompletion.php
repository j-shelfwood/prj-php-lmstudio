<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Response;

use JsonSerializable;
use Shelfwood\LMStudio\DTOs\Response\Common\Choice;
use Shelfwood\LMStudio\DTOs\Response\Common\ModelInfo;
use Shelfwood\LMStudio\DTOs\Response\Common\Runtime;
use Shelfwood\LMStudio\DTOs\Response\Common\Stats;
use Shelfwood\LMStudio\DTOs\Response\Common\Usage;

final readonly class ChatCompletion implements JsonSerializable
{
    /**
     * @param  array<Choice>  $choices
     */
    public function __construct(
        public string $id,
        public string $object,
        public int $created,
        public string $model,
        public array $choices,
        public Usage $usage,
        public ?Stats $stats = null,
        public ?ModelInfo $modelInfo = null,
        public ?Runtime $runtime = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? uniqid('chat_'),
            object: $data['object'] ?? 'chat.completion',
            created: $data['created'] ?? time(),
            model: $data['model'] ?? 'unknown',
            choices: array_map(
                fn (array $choice) => Choice::fromArray($choice),
                $data['choices']
            ),
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : new Usage(0, 0, 0),
            stats: isset($data['stats']) ? Stats::fromArray($data['stats']) : null,
            modelInfo: isset($data['model_info']) ? ModelInfo::fromArray($data['model_info']) : null,
            runtime: isset($data['runtime']) ? Runtime::fromArray($data['runtime']) : null,
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'id' => $this->id,
            'object' => $this->object,
            'created' => $this->created,
            'model' => $this->model,
            'choices' => array_map(
                fn (Choice $choice) => $choice->jsonSerialize(),
                $this->choices
            ),
            'usage' => $this->usage->jsonSerialize(),
            'stats' => $this->stats?->jsonSerialize(),
            'model_info' => $this->modelInfo?->jsonSerialize(),
            'runtime' => $this->runtime?->jsonSerialize(),
        ], fn ($value) => $value !== null);
    }
}
