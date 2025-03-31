<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model\Tool;

class ToolCall
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        /** @var array<string, mixed> */
        public readonly array $arguments = [],
    ) {}

    public static function fromArray(array $data): self
    {
        // Expect OpenAI format: {id: ?, type: ?, function: {name: ?, arguments: ?}}
        if (! isset($data['function']) || ! is_array($data['function'])) {
            throw new \InvalidArgumentException('Invalid tool call structure: Missing or non-array "function" key.');
        }

        $functionData = $data['function'];
        $id = $data['id'] ?? uniqid('tool_'); // Tool call ID is optional but should be present

        // Validate function name exists and is not empty
        if (! isset($functionData['name']) || ! is_string($functionData['name']) || empty($functionData['name'])) {
            throw new \InvalidArgumentException('Invalid tool call structure: Missing, empty, or non-string function name.');
        }
        $name = $functionData['name'];

        // Handle arguments (must be a JSON string representing an object/array)
        $arguments = [];

        if (isset($functionData['arguments'])) {
            if (! is_string($functionData['arguments'])) {
                throw new \InvalidArgumentException('Tool call arguments must be a JSON string.');
            }

            // Allow empty string for empty arguments
            if (! empty($functionData['arguments'])) {
                try {
                    $decoded = json_decode($functionData['arguments'], true, 512, JSON_THROW_ON_ERROR);

                    if (! is_array($decoded)) {
                        // Arguments must decode to an array (JSON object or array)
                        throw new \InvalidArgumentException('Tool call arguments JSON string must decode to an object/array.');
                    }
                    $arguments = $decoded;
                } catch (\JsonException $e) {
                    // If it's not valid JSON, this is an error according to OpenAI spec
                    throw new \InvalidArgumentException('Tool call arguments string is not valid JSON: '.$e->getMessage(), 0, $e);
                }
            }
        }

        return new self($id, $name, $arguments);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => json_encode($this->arguments, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ],
        ];
    }
}
