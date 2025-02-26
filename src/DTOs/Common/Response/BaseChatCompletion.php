<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Common\Response;

use Shelfwood\LMStudio\DTOs\Common\Chat\Message;

abstract readonly class BaseChatCompletion extends BaseResponse
{
    public readonly string $type;

    public readonly Message $message;

    /**
     * @param  array<Choice>  $choices
     */
    public function __construct(
        string $id,
        string $object,
        int $created,
        string $model,
        public array $choices,
        public Usage $usage,
        string $type,
        Message $message
    ) {
        parent::__construct($id, $object, $created, $model);
        $this->type = $type;
        $this->message = $message;
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'choices' => array_map(
                fn (Choice $choice) => $choice->jsonSerialize(),
                $this->choices
            ),
            'usage' => $this->usage->jsonSerialize(),
        ]);
    }

    abstract public static function fromArray(array $data): static;
}
