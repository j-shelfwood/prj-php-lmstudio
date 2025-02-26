<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\OpenAI\Response;

use JsonSerializable;
use Shelfwood\LMStudio\DTOs\Common\Response\BaseTextCompletion;
use Shelfwood\LMStudio\DTOs\Common\Response\Choice;

final readonly class TextCompletion extends BaseTextCompletion implements JsonSerializable
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
    ) {
        parent::__construct($id, $object, $created, $model, $choices);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: $data['id'] ?? uniqid('cmpl_'),
            object: $data['object'] ?? 'text_completion',
            created: $data['created'] ?? time(),
            model: $data['model'] ?? 'unknown',
            choices: array_map(
                fn (array $choice) => Choice::fromArray($choice),
                $data['choices']
            ),
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
        ], fn ($value) => $value !== null);
    }
}
