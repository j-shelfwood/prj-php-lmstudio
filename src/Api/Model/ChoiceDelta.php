<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model;

use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCallDelta;

/**
 * Represents the delta content within a choice of a streaming chunk.
 */
class ChoiceDelta
{
    /**
     * @param Role|null $role The role (usually assistant, only in the first relevant chunk)
     * @param string|null $content Partial or full content string
     * @param ToolCallDelta[]|null $toolCalls Array of tool call fragments in this chunk
     */
    public function __construct(
        public readonly ?Role $role,
        public readonly ?string $content,
        public readonly ?array $toolCalls
    ) {}

    /**
     * Creates a ChoiceDelta from the raw delta array in a chunk.
     *
     * @param array $data The delta data (e.g., $chunk['choices'][0]['delta'])
     */
    public static function fromArray(array $data): self
    {
        $role = isset($data['role']) ? Role::from($data['role']) : null;
        $content = $data['content'] ?? null;
        $toolCalls = null;

        if (isset($data['tool_calls']) && is_array($data['tool_calls'])) {
            $toolCalls = array_map([ToolCallDelta::class, 'fromArray'], $data['tool_calls']);
        }

        return new self($role, $content, $toolCalls);
    }
}