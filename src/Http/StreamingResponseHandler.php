<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http;

use Psr\Http\Message\ResponseInterface;
use Shelfwood\LMStudio\Contracts\StreamingResponseHandlerInterface;
use Shelfwood\LMStudio\DTOs\Common\Chat\Message;
use Shelfwood\LMStudio\DTOs\Common\Response\StreamingResponse;
use Shelfwood\LMStudio\DTOs\Common\Tool\ToolCall;

class StreamingResponseHandler implements StreamingResponseHandlerInterface
{
    /**
     * Handle the streaming response and yield StreamingResponse objects.
     *
     * @return \Generator<int, StreamingResponse, mixed, void>
     */
    public function handle(ResponseInterface $response): \Generator
    {
        return (function () use ($response) {
            $buffer = '';
            $stream = $response->getBody();
            $currentToolCall = null;
            $isJsonComplete = false;

            while (! $stream->eof()) {
                $chunk = $stream->read(1024);

                if ($chunk === '') {
                    break;
                }
                $buffer .= $chunk;

                // Process complete SSE events
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $event = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    foreach ($this->processEvent($event, $currentToolCall, $isJsonComplete) as $response) {
                        yield $response;
                    }
                }
            }

            if (! empty(trim($buffer))) {
                foreach ($this->processEvent($buffer, $currentToolCall, $isJsonComplete) as $response) {
                    yield $response;
                }
            }

            yield StreamingResponse::done();
        })();
    }

    /**
     * Process an SSE event
     *
     * @param  string  $event  The event data
     * @param  array<string, mixed>|null  $currentToolCall  Reference to current tool call state
     * @param  bool  $isJsonComplete  Reference to JSON completion flag
     * @return \Generator<int, StreamingResponse, mixed, void>
     */
    private function processEvent(
        string $event,
        ?array &$currentToolCall,
        bool &$isJsonComplete
    ): \Generator {
        $lines = explode("\n", $event);

        foreach ($lines as $line) {
            if (empty(trim($line)) || ! str_starts_with($line, 'data: ')) {
                continue;
            }

            $data = substr($line, 6);

            if ($data === '[DONE]') {
                return;
            }

            try {
                $json = json_decode($data, false, 512, JSON_THROW_ON_ERROR);

                // Handle tool calls
                $toolCallDelta = $json->choices[0]->delta->tool_calls[0] ?? null;

                if ($toolCallDelta !== null) {
                    $toolCallResponse = $this->processToolCallDelta($toolCallDelta, $currentToolCall, $isJsonComplete);

                    if ($toolCallResponse !== null) {
                        yield $toolCallResponse;
                    }
                }
                // Handle message content
                elseif ($content = $json->choices[0]->delta->content ?? null) {
                    yield StreamingResponse::fromMessage(
                        Message::fromArray([
                            'role' => 'assistant',
                            'content' => $content,
                        ])
                    );
                }
            } catch (\JsonException $e) {
                // Skip invalid JSON
            }
        }
    }

    /**
     * Process a tool call delta
     *
     * @param  object  $delta  The tool call delta object
     * @param  array<string, mixed>|null  $currentToolCall  Reference to current tool call state
     * @param  bool  $isJsonComplete  Reference to JSON completion flag
     */
    private function processToolCallDelta(
        object $delta,
        ?array &$currentToolCall,
        bool &$isJsonComplete
    ): ?StreamingResponse {
        if (! isset($currentToolCall)) {
            $currentToolCall = [
                'id' => $delta->id ?? uniqid('call_'),
                'type' => $delta->type ?? 'function',
                'function' => [
                    'name' => $delta->function->name ?? '',
                    'arguments' => '',
                ],
            ];
        }

        if (isset($delta->function->name)) {
            $currentToolCall['function']['name'] = $delta->function->name;
        }

        if (isset($delta->function->arguments)) {
            $currentToolCall['function']['arguments'] .= $delta->function->arguments;

            // Check if arguments form a complete JSON object
            try {
                json_decode($currentToolCall['function']['arguments'], true, 512, JSON_THROW_ON_ERROR);
                $isJsonComplete = true;
            } catch (\JsonException $e) {
                $isJsonComplete = false;
            }

            // Only yield when we have a complete JSON object
            if ($isJsonComplete && ! empty($currentToolCall['function']['name'])) {
                $response = StreamingResponse::fromToolCall(
                    ToolCall::fromArray($currentToolCall)
                );

                $currentToolCall = null;
                $isJsonComplete = false;

                return $response;
            }
        }

        return null;
    }
}
