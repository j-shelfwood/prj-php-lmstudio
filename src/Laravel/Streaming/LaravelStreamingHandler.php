<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel\Streaming;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * @var array Additional data to include in server-sent events
     */
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
     * @param  array  $data  The additional data
     */
    public function setAdditionalEventData(array $data): self
    {
        $this->additionalEventData = $data;

        return $this;
    }

    /**
     * Create a streamed response from the streaming handler.
     *
     * @param  callable|null  $callback  Additional callback to execute for each chunk
     * @return StreamedResponse The streamed response
     */
    public function toStreamedResponse(?callable $callback = null): StreamedResponse
    {
        return new StreamedResponse(function () use ($callback): void {
            // Set headers for SSE if enabled
            if ($this->emitServerSentEvents) {
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no'); // Disable buffering for Nginx
            }

            // Store original callbacks
            $originalOnStart = $this->onStart;
            $originalOnContent = $this->onContent;
            $originalOnToolCall = $this->onToolCall;
            $originalOnEnd = $this->onEnd;
            $originalOnError = $this->onError;

            // Set up SSE callbacks
            $this->onStart(function () use ($originalOnStart, $callback): void {
                if ($this->emitServerSentEvents) {
                    $this->sendEvent(['type' => 'start']);
                }

                if ($originalOnStart) {
                    call_user_func($originalOnStart);
                }

                if ($callback) {
                    call_user_func($callback, ['type' => 'start']);
                }

                flush();
            });

            $this->onContent(function ($content, $buffer, $isComplete) use ($originalOnContent, $callback): void {
                if ($this->emitServerSentEvents) {
                    $this->sendEvent([
                        'type' => 'content',
                        'content' => $content,
                        'buffer' => $buffer,
                        'isComplete' => $isComplete,
                    ]);
                } else {
                    echo $content;
                }

                if ($originalOnContent) {
                    call_user_func($originalOnContent, $content, $buffer, $isComplete);
                }

                if ($callback) {
                    call_user_func($callback, [
                        'type' => 'content',
                        'content' => $content,
                        'buffer' => $buffer,
                        'isComplete' => $isComplete,
                    ]);
                }

                flush();
            });

            $this->onToolCall(function ($toolCall, $index, $isComplete) use ($originalOnToolCall, $callback): void {
                if ($this->emitServerSentEvents) {
                    $this->sendEvent([
                        'type' => 'tool_call',
                        'toolCall' => $toolCall,
                        'index' => $index,
                        'isComplete' => $isComplete,
                    ]);
                }

                if ($originalOnToolCall) {
                    call_user_func($originalOnToolCall, $toolCall, $index, $isComplete);
                }

                if ($callback) {
                    call_user_func($callback, [
                        'type' => 'tool_call',
                        'toolCall' => $toolCall,
                        'index' => $index,
                        'isComplete' => $isComplete,
                    ]);
                }

                flush();
            });

            $this->onEnd(function ($buffer, $toolCalls) use ($originalOnEnd, $callback): void {
                if ($this->emitServerSentEvents) {
                    $this->sendEvent([
                        'type' => 'end',
                        'buffer' => $buffer,
                        'toolCalls' => $toolCalls,
                    ]);
                }

                if ($originalOnEnd) {
                    call_user_func($originalOnEnd, $buffer, $toolCalls);
                }

                if ($callback) {
                    call_user_func($callback, [
                        'type' => 'end',
                        'buffer' => $buffer,
                        'toolCalls' => $toolCalls,
                    ]);
                }

                flush();
            });

            $this->onError(function ($error, $buffer, $toolCalls) use ($originalOnError, $callback): void {
                if ($this->emitServerSentEvents) {
                    $this->sendEvent([
                        'type' => 'error',
                        'error' => $error->getMessage(),
                        'buffer' => $buffer,
                        'toolCalls' => $toolCalls,
                    ]);
                }

                if ($originalOnError) {
                    call_user_func($originalOnError, $error, $buffer, $toolCalls);
                }

                if ($callback) {
                    call_user_func($callback, [
                        'type' => 'error',
                        'error' => $error->getMessage(),
                        'buffer' => $buffer,
                        'toolCalls' => $toolCalls,
                    ]);
                }

                flush();
            });

            // Keep the connection alive until the client disconnects
            while (true) {
                if (connection_aborted()) {
                    break;
                }

                // Sleep to prevent CPU usage
                usleep(100000); // 100ms
            }
        }, 200, [
            'Content-Type' => $this->emitServerSentEvents ? 'text/event-stream' : 'text/plain',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable buffering for Nginx
        ]);
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

    /**
     * Create a response that broadcasts events using Laravel's event system.
     *
     * @param  string  $channel  The channel to broadcast on
     * @param  string  $eventName  The event name
     * @return Response The response
     */
    public function toBroadcastResponse(string $channel, string $eventName): Response
    {
        // Store original callbacks
        $originalOnStart = $this->onStart;
        $originalOnContent = $this->onContent;
        $originalOnToolCall = $this->onToolCall;
        $originalOnEnd = $this->onEnd;
        $originalOnError = $this->onError;

        // Set up broadcasting callbacks
        $this->onStart(function () use ($originalOnStart, $channel, $eventName): void {
            Event::dispatch($channel, [
                'event' => $eventName,
                'data' => ['type' => 'start'],
            ]);

            if ($originalOnStart) {
                call_user_func($originalOnStart);
            }
        });

        $this->onContent(function ($content, $buffer, $isComplete) use ($originalOnContent, $channel, $eventName): void {
            Event::dispatch($channel, [
                'event' => $eventName,
                'data' => [
                    'type' => 'content',
                    'content' => $content,
                    'buffer' => $buffer,
                    'isComplete' => $isComplete,
                ],
            ]);

            if ($originalOnContent) {
                call_user_func($originalOnContent, $content, $buffer, $isComplete);
            }
        });

        $this->onToolCall(function ($toolCall, $index, $isComplete) use ($originalOnToolCall, $channel, $eventName): void {
            Event::dispatch($channel, [
                'event' => $eventName,
                'data' => [
                    'type' => 'tool_call',
                    'toolCall' => $toolCall,
                    'index' => $index,
                    'isComplete' => $isComplete,
                ],
            ]);

            if ($originalOnToolCall) {
                call_user_func($originalOnToolCall, $toolCall, $index, $isComplete);
            }
        });

        $this->onEnd(function ($buffer, $toolCalls) use ($originalOnEnd, $channel, $eventName): void {
            Event::dispatch($channel, [
                'event' => $eventName,
                'data' => [
                    'type' => 'end',
                    'buffer' => $buffer,
                    'toolCalls' => $toolCalls,
                ],
            ]);

            if ($originalOnEnd) {
                call_user_func($originalOnEnd, $buffer, $toolCalls);
            }
        });

        $this->onError(function ($error, $buffer, $toolCalls) use ($originalOnError, $channel, $eventName): void {
            Event::dispatch($channel, [
                'event' => $eventName,
                'data' => [
                    'type' => 'error',
                    'error' => $error->getMessage(),
                    'buffer' => $buffer,
                    'toolCalls' => $toolCalls,
                ],
            ]);

            if ($originalOnError) {
                call_user_func($originalOnError, $error, $buffer, $toolCalls);
            }
        });

        return new Response('Broadcasting started', 200);
    }
}
