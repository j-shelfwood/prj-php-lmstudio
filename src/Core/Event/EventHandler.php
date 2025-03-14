<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Event;

/**
 * Handler for managing event callbacks.
 */
class EventHandler
{
    /**
     * @var array<string, array<callable>> The registered event handlers
     */
    private array $handlers = [];

    /**
     * Register a handler for an event.
     *
     * @param  string  $event  The event name
     * @param  callable  $handler  The handler function
     */
    public function on(string $event, callable $handler): void
    {
        if (! isset($this->handlers[$event])) {
            $this->handlers[$event] = [];
        }

        $this->handlers[$event][] = $handler;
    }

    /**
     * Trigger an event.
     *
     * @param  string  $event  The event name
     * @param  mixed  ...$args  The event arguments
     */
    public function trigger(string $event, ...$args): void
    {
        if (! isset($this->handlers[$event])) {
            return;
        }

        foreach ($this->handlers[$event] as $handler) {
            $handler(...$args);
        }
    }

    /**
     * Check if an event has callbacks.
     *
     * @param  string  $event  The event name
     * @return bool Whether the event has callbacks
     */
    public function hasCallbacks(string $event): bool
    {
        return isset($this->handlers[$event]) && ! empty($this->handlers[$event]);
    }

    /**
     * Get all callbacks for an event.
     *
     * @param  string  $event  The event name
     * @return array<callable> The callbacks
     */
    public function getCallbacks(string $event): array
    {
        return $this->handlers[$event] ?? [];
    }

    /**
     * Clear all callbacks for an event.
     *
     * @param  string  $event  The event name
     */
    public function clearCallbacks(string $event): self
    {
        unset($this->handlers[$event]);

        return $this;
    }

    /**
     * Clear all callbacks.
     */
    public function clearAllCallbacks(): self
    {
        $this->handlers = [];

        return $this;
    }
}
