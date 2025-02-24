<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http;

use Psr\Http\Message\ResponseInterface;
use Shelfwood\LMStudio\Contracts\StreamingResponseHandlerInterface;
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Tool\ToolCall;

class StreamingResponseHandler implements StreamingResponseHandlerInterface
{
    /**
     * Handle the streaming response and yield messages or tool calls.
     *
     * @return \Generator<Message|ToolCall>
     */
    public function handle(ResponseInterface $response): \Generator
    {
        $buffer = '';
        $stream = $response->getBody();
        $currentToolCall = null;

        while (! $stream->eof()) {
            $chunk = $stream->read(1024);

            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);

                if (empty(trim($line))) {
                    continue;
                }

                if (str_starts_with($line, 'data: ')) {
                    $line = substr($line, 6);
                }

                if ($line === '[DONE]') {
                    continue;
                }

                try {
                    $data = json_decode($line);

                    if (! is_object($data)) {
                        continue;
                    }

                    $toolCallDelta = $data->choices[0]?->delta?->tool_calls[0] ?? null;

                    if ($toolCallDelta !== null) {
                        if (! isset($currentToolCall)) {
                            $currentToolCall = [
                                'id' => $toolCallDelta->id ?? null,
                                'type' => $toolCallDelta->type ?? 'function',
                                'function' => [
                                    'name' => $toolCallDelta->function?->name ?? '',
                                    'arguments' => '',
                                ],
                            ];
                        }

                        if (isset($toolCallDelta->function?->name)) {
                            $currentToolCall['function']['name'] = $toolCallDelta->function->name;
                        }

                        if (isset($toolCallDelta->function?->arguments)) {
                            $currentToolCall['function']['arguments'] .= $toolCallDelta->function->arguments;

                            // Only yield if we have a complete JSON object
                            if (
                                $currentToolCall['function']['name'] &&
                                $this->isCompleteJson($currentToolCall['function']['arguments'])
                            ) {
                                yield ToolCall::fromArray($currentToolCall);
                                $currentToolCall = null;
                            }
                        }
                    } elseif ($content = $data->choices[0]?->delta?->content ?? null) {
                        yield Message::fromArray([
                            'role' => 'assistant',
                            'content' => $content,
                        ]);
                    }
                } catch (\JsonException $e) {
                    continue;
                }
            }
        }

        if (! empty(trim($buffer))) {
            try {
                $data = json_decode($buffer);

                if (is_object($data)) {
                    yield $data;
                }
            } catch (\JsonException $e) {
                // Ignore invalid JSON at the end of the stream
            }
        }
    }

    /**
     * Check if a string is a complete JSON object
     */
    private function isCompleteJson(string $json): bool
    {
        try {
            json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return true;
        } catch (\JsonException $e) {
            return false;
        }
    }
}
