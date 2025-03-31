<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel\Streaming;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LaravelStreamingHandler extends StreamingHandler
{
    /**
     * @var bool Whether to emit server-sent events
     */
    protected bool $emitServerSentEvents = false;

    /**
     * @var string The event name for server-sent events
     */
    protected string $eventName = 'message';

    /** @var array<string, mixed> */
    protected array $additionalEventData = [];

    /**
     * Set whether to emit server-sent events.
     *
     * @param  bool  $emit  Whether to emit server-sent events
     */
    public function emitServerSentEvents(bool $emit = true): self
    {
        $this->emitServerSentEvents = $emit;

        return $this;
    }

    /**
     * Set the event name for server-sent events.
     *
     * @param  string  $eventName  The event name
     */
    public function setEventName(string $eventName): self
    {
        $this->eventName = $eventName;

        return $this;
    }

    /**
     * Set additional data to include in server-sent events.
     *
     * @param  array<string, mixed>  $data  The additional data
     */
    public function setAdditionalEventData(array $data): self
    {
        $this->additionalEventData = $data;

        return $this;
    }

    /**
     * Create a streamed response for use in a Laravel controller.
     *
     * @param  callable|null  $responseCallback  Optional callback to process data before sending. Receives event name and data array.
     */
    public function toStreamedResponse(?callable $responseCallback = null): StreamedResponse
    {
        return new StreamedResponse(function () use ($responseCallback): void {
            // Setup SSE headers if configured
            if ($this->emitServerSentEvents) {
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no'); // Disable buffering for Nginx
            }

            // Define event handlers that format and echo/send data
            $this->on('stream_start', function ($chunk) use ($responseCallback): void {
                $data = ['type' => 'start'];

                if ($responseCallback) {
                    $responseCallback('start', $data);
                }
                $this->sendResponseData($data);
            });

            $this->on('stream_content', function (string $content, $chunk) use ($responseCallback): void {
                $data = ['type' => 'content', 'content' => $content];

                if ($responseCallback) {
                    $responseCallback('content', $data);
                }
                $this->sendResponseData($data);
            });

            // Assuming base handler emits 'stream_tool_call_start', 'stream_tool_call_delta', 'stream_tool_call_end'
            $this->on('stream_tool_call_start', function (int $index, ?string $id, ?string $type, $chunk) use ($responseCallback): void {
                $data = ['type' => 'tool_start', 'index' => $index, 'id' => $id, 'tool_type' => $type];

                if ($responseCallback) {
                    $responseCallback('tool_start', $data);
                }
                $this->sendResponseData($data);
            });

            $this->on('stream_tool_call_delta', function (int $index, $delta, $chunk) use ($responseCallback): void {
                $data = ['type' => 'tool_delta', 'index' => $index, 'delta' => $delta->toArray()]; // Convert delta to array

                if ($responseCallback) {
                    $responseCallback('tool_delta', $data);
                }
                $this->sendResponseData($data);
            });

            $this->on('stream_tool_call_end', function (int $index, ToolCall $toolCall) use ($responseCallback): void {
                $data = ['type' => 'tool_end', 'index' => $index, 'tool_call' => $toolCall->toArray()];

                if ($responseCallback) {
                    $responseCallback('tool_end', $data);
                }
                $this->sendResponseData($data);
            });

            $this->on('stream_end', function (?array $finalToolCalls, $chunk) use ($responseCallback): void {
                $data = ['type' => 'end', 'tool_calls' => $finalToolCalls ? array_map(fn (ToolCall $tc) => $tc->toArray(), $finalToolCalls) : null];

                if ($responseCallback) {
                    $responseCallback('end', $data);
                }
                $this->sendResponseData($data);
            });

            $this->on('stream_error', function (Throwable $error, $chunk) use ($responseCallback): void {
                $data = ['type' => 'error', 'error' => $error->getMessage()];

                if ($responseCallback) {
                    $responseCallback('error', $data);
                }
                $this->sendResponseData($data);
            });

            // Keep connection alive (optional, consider removing if not needed)
            // while (connection_aborted() === 0) {
            //     usleep(100000); // Sleep 100ms
            // }

        }, 200, [
            'Content-Type' => $this->emitServerSentEvents ? 'text/event-stream' : 'text/plain',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Helper to send data either as plain text or SSE.
     *
     * @param  array<string, mixed>  $data
     */
    private function sendResponseData(array $data): void
    {
        if ($this->emitServerSentEvents) {
            $this->sendEvent($data);
        } elseif (isset($data['content']) && is_string($data['content'])) {
            // For plain text, only echo content chunks
            echo $data['content'];
        }

        // Flush the output buffer
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Send a server-sent event.
     *
     * @param  array  $data  The event data
     */
    protected function sendEvent(array $data): void
    {
        $data = array_merge($data, $this->additionalEventData);
        $json = json_encode($data);

        echo "event: {$this->eventName}\n";
        echo "data: {$json}\n\n";
    }
}
