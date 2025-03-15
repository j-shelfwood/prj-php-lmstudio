<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model\Tool;

class ToolDefinition
{
    private string $name;

    private string $description;

    private ToolParameters $parameters;

    public function __construct(string $name, string $description, ToolParameters $parameters)
    {
        $this->name = $name;
        $this->description = $description;
        $this->parameters = $parameters;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getParameters(): ToolParameters
    {
        return $this->parameters;
    }

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
