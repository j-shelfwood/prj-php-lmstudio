<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Common\Response;

use JsonSerializable;

abstract readonly class BaseResponse implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $object,
        public int $created,
        public string $model,
    ) {}

    abstract public static function fromArray(array $data): static;

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'object' => $this->object,
            'created' => $this->created,
            'model' => $this->model,
        ];
    }

    protected static function getBaseFields(array $data): array
    {
        return [
            'id' => $data['id'] ?? uniqid(),
            'object' => $data['object'] ?? 'unknown',
            'created' => $data['created'] ?? time(),
            'model' => $data['model'] ?? 'unknown',
        ];
    }
}
