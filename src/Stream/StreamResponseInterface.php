<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Stream;

/**
 * Interface for stream responses.
 */
interface StreamResponseInterface
{
    /**
     * Process the stream with a callback for each chunk.
     */
    public function process(callable $callback): void;

    /**
     * Get the current state.
     */
    public function getState(): StreamState;

    /**
     * Get the accumulated content.
     */
    public function getContent(): string;

    /**
     * Get the received tool calls.
     */
    public function getToolCalls(): array;
}
