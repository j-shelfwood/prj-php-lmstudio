<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Utilities;

use Shelfwood\LMStudio\ValueObjects\FunctionCall;
use Shelfwood\LMStudio\ValueObjects\ToolCall;

/**
 * Utility class for extracting tool calls from API responses.
 */
class ToolCallExtractor
{
    /**
     * Extract tool calls from an API response.
     *
     * @param  array|object  $response  The API response
     * @param  bool  $convertToObjects  Whether to convert the tool calls to ToolCall objects
     * @return array The extracted tool calls
     */
    public static function extract($response, bool $convertToObjects = true): array
    {
        $toolCallsData = [];

        if (is_array($response)) {
            $toolCallsData = $response['choices'][0]['message']['toolCalls']
                ?? $response['choices'][0]['message']['tool_calls']
                ?? [];
        } else {
            $toolCallsData = $response->choices[0]->message->toolCalls
                ?? $response->choices[0]->message->tool_calls
                ?? [];
        }

        if (! $convertToObjects) {
            return $toolCallsData;
        }

        // Convert array tool calls to ToolCall objects
        $toolCalls = [];

        foreach ($toolCallsData as $data) {
            if ($data instanceof ToolCall) {
                $toolCalls[] = $data;
            } else {
                $id = $data['id'] ?? '';
                $type = $data['type'] ?? 'function';
                $name = $data['function']['name'] ?? '';
                $arguments = $data['function']['arguments'] ?? '{}';

                $toolCalls[] = new ToolCall(
                    $id,
                    $type,
                    new FunctionCall($name, $arguments)
                );
            }
        }

        return $toolCalls;
    }
}
