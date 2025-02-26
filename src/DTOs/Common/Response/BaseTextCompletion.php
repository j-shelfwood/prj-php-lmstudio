<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Common\Response;

abstract readonly class BaseTextCompletion extends BaseResponse
{
    /**
     * @param  array<Choice>  $choices
     */
    public function __construct(
        string $id,
        string $object,
        int $created,
        string $model,
        public array $choices,
    ) {
        parent::__construct($id, $object, $created, $model);
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'choices' => array_map(
                fn (Choice $choice) => $choice->jsonSerialize(),
                $this->choices
            ),
        ]);
    }

    public static function fromArray(array $data): static
    {
        return new static(
            id: $data['id'],
            object: $data['object'],
            created: $data['created'],
            model: $data['model'],
            choices: array_map(
                fn (array $choice) => Choice::fromArray($choice),
                $data['choices']
            ),
        );
    }
}
