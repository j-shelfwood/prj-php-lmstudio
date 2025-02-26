<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\LMStudio\Response;

use Shelfwood\LMStudio\DTOs\Common\Chat\Message;
use Shelfwood\LMStudio\DTOs\Common\Chat\Role;
use Shelfwood\LMStudio\DTOs\Common\Response\BaseChatCompletion;
use Shelfwood\LMStudio\DTOs\Common\Response\Choice;
use Shelfwood\LMStudio\DTOs\Common\Response\Usage;
use Shelfwood\LMStudio\DTOs\LMStudio\Response\Common\ModelInfo;
use Shelfwood\LMStudio\DTOs\LMStudio\Response\Common\Runtime;
use Shelfwood\LMStudio\DTOs\LMStudio\Response\Common\Stats;

final readonly class ChatCompletion extends BaseChatCompletion
{
    /**
     * @param  array<Choice>  $choices
     */
    public function __construct(
        string $id,
        string $object,
        int $created,
        string $model,
        array $choices,
        Usage $usage,
        public ?Stats $stats = null,
        public ?ModelInfo $modelInfo = null,
        public ?Runtime $runtime = null,
    ) {
        parent::__construct(
            id: $id,
            object: $object,
            created: $created,
            model: $model,
            choices: $choices,
            usage: $usage,
            type: 'chat.completion',
            message: $choices[0]->message ?? new Message(Role::ASSISTANT, '')
        );
    }

    public static function fromArray(array $data): static
    {
        $baseFields = parent::getBaseFields($data);
        $choices = array_map(
            fn (array $choice) => Choice::fromArray($choice),
            $data['choices']
        );
        $usage = isset($data['usage']) ? Usage::fromArray($data['usage']) : new Usage(0, 0, 0);
        $stats = isset($data['stats']) ? Stats::fromArray($data['stats']) : null;
        $modelInfo = isset($data['model_info']) ? ModelInfo::fromArray($data['model_info']) : null;
        $runtime = isset($data['runtime']) ? Runtime::fromArray($data['runtime']) : null;

        return new self(
            $baseFields['id'],
            $baseFields['object'],
            $baseFields['created'],
            $baseFields['model'],
            $choices,
            $usage,
            $stats,
            $modelInfo,
            $runtime,
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter(array_merge(parent::jsonSerialize(), [
            'stats' => $this->stats?->jsonSerialize(),
            'model_info' => $this->modelInfo?->jsonSerialize(),
            'runtime' => $this->runtime?->jsonSerialize(),
        ]), fn ($value) => $value !== null);
    }
}
