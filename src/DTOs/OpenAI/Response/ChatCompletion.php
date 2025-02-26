<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\OpenAI\Response;

use Shelfwood\LMStudio\DTOs\Common\Chat\Message;
use Shelfwood\LMStudio\DTOs\Common\Chat\Role;
use Shelfwood\LMStudio\DTOs\Common\Response\BaseChatCompletion;
use Shelfwood\LMStudio\DTOs\Common\Response\Choice;
use Shelfwood\LMStudio\DTOs\Common\Response\Usage;

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

        return new self(
            $baseFields['id'],
            $baseFields['object'],
            $baseFields['created'],
            $baseFields['model'],
            $choices,
            $usage,
        );
    }
}
