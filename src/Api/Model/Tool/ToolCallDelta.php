<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model\Tool;

/**
 * Represents a fragmented tool call delta within a streaming chunk.
 */
class ToolCallDelta
{
    /**
     * @param int $index The index of the tool call in the list
     * @param string|null $id The ID of the tool call, usually present in the first chunk for the call
     * @param string|null $type The type of the tool call, typically 'function'
     * @param string|null $functionName Partial or full name of the function
     * @param string|null $functionArguments Partial or full arguments JSON string
     */
    public function __construct(
        public readonly int $index,
        public readonly ?string $id,
        public readonly ?string $type,
        public readonly ?string $functionName,
        public readonly ?string $functionArguments
    ) {}

    /**
     * Creates a ToolCallDelta from a raw array fragment.
     *
     * @param array $data The tool call delta data from the chunk (e.g., $chunk['choices'][0]['delta']['tool_calls'][0])
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['index'])) {
            // Index is essential for assembling fragments
            throw new \InvalidArgumentException('Tool call delta must include an index.');
        }

        $index = (int) $data['index'];
        $id = $data['id'] ?? null;
        $type = $data['type'] ?? null; // Should usually default to 'function' if set

        $functionName = $data['function']['name'] ?? null;
        $functionArguments = $data['function']['arguments'] ?? null;

        return new self(
            index: $index,
            id: $id,
            type: $type,
            functionName: $functionName,
            functionArguments: $functionArguments
        );
    }
}