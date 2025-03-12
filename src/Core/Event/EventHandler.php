<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Event;

/**
 * Handler for managing event callbacks.
 */
class EventHandler
{
    /**
     * @var array<string, array<callable>> Map of event names to callbacks
     */
    private array $callbacks = [];

    /**
     * Register a callback for an event.
     *
     * @param  string  $event  The event name
     * @param  callable  $callback  The callback function
     */
    public function registerCallback(string $event, callable $callback): self
    {
        if (! isset($this->callbacks[$event])) {
            $this->callbacks[$event] = [];
        }

        $this->callbacks[$event][] = $callback;

        return $this;
    }

    /**
     * Check if an event has callbacks.
     *
     * @param  string  $event  The event name
     * @return bool Whether the event has callbacks
     */
    public function hasCallbacks(string $event): bool
    {
        return isset($this->callbacks[$event]) && ! empty($this->callbacks[$event]);
    }

    /**
     * Trigger an event.
     *
     * @param  string  $event  The event name
     * @param  mixed  ...$args  The arguments to pass to the callbacks
     */
    public function trigger(string $event, ...$args): void
    {
        if (! $this->hasCallbacks($event)) {
            return;
        }

        foreach ($this->callbacks[$event] as $callback) {
            call_user_func_array($callback, $args);
        }
    }

    /**
     * Get all callbacks for an event.
     *
     * @param  string  $event  The event name
     * @return array<callable> The callbacks
     */
    public function getCallbacks(string $event): array
    {
        return $this->callbacks[$event] ?? [];
    }

    /**
     * Clear all callbacks for an event.
     *
     * @param  string  $event  The event name
     */
    public function clearCallbacks(string $event): self
    {
        unset($this->callbacks[$event]);

        return $this;
    }

    /**
     * Clear all callbacks.
     */
    public function clearAllCallbacks(): self
    {
        $this->callbacks = [];

        return $this;
    }
}
