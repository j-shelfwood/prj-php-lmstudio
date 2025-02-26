<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\DTOs\Common\Tool;

use JsonException;
use JsonSerializable;

final readonly class ToolFunction implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string>  $required
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters = [],
        public array $required = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? '',
            parameters: $data['parameters'] ?? [],
            required: $data['required'] ?? [],
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => [
                'type' => 'object',
                'properties' => $this->parameters,
                'required' => $this->required,
            ],
        ], fn ($value) => ! empty($value));
    }

    /**
     * @throws JsonException
     */
    public function validateArguments(string $arguments): array
    {
        $args = json_decode($arguments, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($args)) {
            throw new JsonException('Arguments must be a valid JSON object');
        }

        foreach ($this->required as $requiredParam) {
            if (! isset($args[$requiredParam])) {
                throw new JsonException("Missing required parameter: {$requiredParam}");
            }
        }

        return $args;
    }
}
