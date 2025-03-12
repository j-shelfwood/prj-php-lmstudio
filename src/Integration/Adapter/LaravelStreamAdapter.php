<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Integration\Adapter\Laravel;

use Illuminate\Http\Response;
use Shelfwood\LMStudio\Stream\StreamResponse;

/**
 * Adapter for Laravel streaming responses.
 */
class LaravelStreamAdapter
{
    /**
     * Convert a StreamResponse to a Laravel response.
     */
    public function toResponse(StreamResponse $streamResponse): Response
    {
        return response()->stream(function () use ($streamResponse): void {
            $streamResponse->process(function ($chunk): void {
                echo 'data: '.json_encode($chunk)."\n\n";
                ob_flush();
                flush();
            });
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
