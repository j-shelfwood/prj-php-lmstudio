<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model\Tool;

class ToolCall
{
    private string $id;

    private string $name;

    private array $arguments;

    public function __construct(string $id, string $name, array $arguments = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->arguments = $arguments;
    }

    public static function fromArray(array $data): self
    {
        // Handle OpenAI format
        if (isset($data['function'])) {
            $id = $data['id'] ?? uniqid('tool_');

            // Validate function name exists and is not empty
            if (! isset($data['function']['name']) || empty($data['function']['name'])) {
                throw new \InvalidArgumentException('Tool call function name is required and cannot be empty');
            }
            $name = $data['function']['name'];

            // Handle arguments
            $arguments = [];

            if (isset($data['function']['arguments'])) {
                if (is_string($data['function']['arguments'])) {
                    if (empty($data['function']['arguments'])) {
                        $arguments = [];
                    } else {
                        // Try to parse as complete JSON first
                        try {
                            $decoded = json_decode($data['function']['arguments'], true, 512, JSON_THROW_ON_ERROR);

                            if (is_array($decoded)) {
                                $arguments = $decoded;
                            } else {
                                $arguments = ['dummy' => 'none'];
                            }
                        } catch (\JsonException $e) {
                            // If it's not complete JSON, use default arguments
                            $arguments = ['dummy' => 'none'];
                        }
                    }
                } elseif (is_array($data['function']['arguments'])) {
                    $arguments = $data['function']['arguments'];
                }
            }

            return new self($id, $name, $arguments);
        }

        // Handle direct format
        $id = $data['id'] ?? uniqid('tool_');

        // Validate name exists and is not empty
        if (! isset($data['name']) || empty($data['name'])) {
            throw new \InvalidArgumentException('Tool call name is required and cannot be empty');
        }
        $name = $data['name'];

        // Handle arguments
        $arguments = [];

        if (isset($data['arguments'])) {
            if (is_string($data['arguments'])) {
                if (empty($data['arguments'])) {
                    $arguments = [];
                } else {
                    // Try to parse as complete JSON first
                    try {
                        $decoded = json_decode($data['arguments'], true, 512, JSON_THROW_ON_ERROR);

                        if (is_array($decoded)) {
                            $arguments = $decoded;
                        } else {
                            $arguments = ['dummy' => 'none'];
                        }
                    } catch (\JsonException $e) {
                        // If it's not complete JSON, use default arguments
                        $arguments = ['dummy' => 'none'];
                    }
                }
            } elseif (is_array($data['arguments'])) {
                $arguments = $data['arguments'];
            }
        }

        return new self($id, $name, $arguments);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): array
    {
        return $this->arguments;
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

    /**
     * Get the type of the tool call.
     */
    public function getType(): string
    {
        return 'function';
    }

    /**
     * Get the function object.
     */
    public function getFunction(): object
    {
        return new class($this->name, $this->arguments)
        {
            private string $name;

            private array $arguments;

            public function __construct(string $name, array $arguments)
            {
                $this->name = $name;
                $this->arguments = $arguments;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getArguments(): string
            {
                return json_encode($this->arguments);
            }
        };
    }
}
