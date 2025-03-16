<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Model\Tool;

use Shelfwood\LMStudio\Api\Exception\ToolCallException;

class ToolCallFormatter
{
    private const TOOL_CALL_PATTERN = '/<tool_call>(.*?)<\/tool_call>/s';

    public function formatSystemPrompt(array $tools): string
    {
        $formattedTools = array_map(function ($tool) {
            return [
                'type' => 'function',
                'function' => [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'parameters' => $tool->getParameters()->toArray(),
                ],
            ];
        }, $tools);

        $toolsJson = json_encode($formattedTools, JSON_PRETTY_PRINT);

        return <<<EOT
You are a helpful assistant that can use tools to help answer questions.

# Tools

You may call one or more functions to assist with the user query.

You are provided with function signatures within <tools></tools> XML tags:
<tools>
{$toolsJson}
</tools>

For each function call, return a json object with function name and arguments within <tool_call></tool_call> XML tags:
<tool_call>
{"name": <function-name>, "arguments": <args-json-object>}
</tool_call>
EOT;
    }

    public function parseToolCalls(string $content): array
    {
        $matches = [];

        if (! preg_match_all(self::TOOL_CALL_PATTERN, $content, $matches)) {
            return [];
        }

        $toolCalls = [];

        foreach ($matches[1] as $match) {
            try {
                $toolCall = json_decode($match, true, 512, JSON_THROW_ON_ERROR);

                if (! isset($toolCall['name']) || ! isset($toolCall['arguments'])) {
                    throw ToolCallException::invalidToolCallFormat($toolCall, ['content' => $content]);
                }
                $toolCalls[] = [
                    'id' => uniqid('call_'),
                    'type' => 'function',
                    'function' => [
                        'name' => $toolCall['name'],
                        'arguments' => is_string($toolCall['arguments'])
                            ? $toolCall['arguments']
                            : json_encode($toolCall['arguments']),
                    ],
                ];
            } catch (\JsonException $e) {
                throw ToolCallException::malformedToolCallContent($match);
            }
        }

        return $toolCalls;
    }

    public function formatToolCall(string $name, array $arguments): string
    {
        $toolCall = [
            'name' => $name,
            'arguments' => $arguments,
        ];

        return '<tool_call>'.json_encode($toolCall).'</tool_call>';
    }
}
