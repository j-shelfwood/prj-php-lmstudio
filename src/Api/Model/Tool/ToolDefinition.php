<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model\Tool;

class ToolDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly ToolParameters $parameters
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters->toArray(),
        ];
    }

    public static function fromArray(array $data): self
    {
        $parameters = new ToolParameters;

        if (isset($data['parameters']['properties'])) {
            foreach ($data['parameters']['properties'] as $name => $property) {
                $parameters->addProperty($name, new ToolParameter($property['type'], $property['description']));
            }
        }

        return new self(
            $data['name'],
            $data['description'],
            $parameters
        );
    }
}
