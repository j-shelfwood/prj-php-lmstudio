<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Tools;

use Shelfwood\LMStudio\ValueObjects\Tool;
use Shelfwood\LMStudio\ValueObjects\ToolCall;

/**
 * Registry for tools and their execution handlers.
 */
class ToolRegistry
{
    /**
     * @var array<string, array{tool: Tool, handler: callable}>
     */
    private array $tools = [];

    /**
     * Register a tool with its execution handler.
     */
    public function register(Tool $tool, callable $handler): self
    {
        $this->tools[$tool->function->name] = [
            'tool' => $tool,
            'handler' => $handler,
        ];

        return $this;
    }

    /**
     * Get all registered tools.
     *
     * @return array<Tool>
     */
    public function getTools(): array
    {
        return array_values(array_map(fn ($item) => $item['tool'], $this->tools));
    }

    /**
     * Execute a tool call.
     */
    public function execute(ToolCall $toolCall): mixed
    {
        $name = $toolCall->function->name;

        if (! isset($this->tools[$name])) {
            throw new \InvalidArgumentException("Tool '{$name}' is not registered");
        }

        $handler = $this->tools[$name]['handler'];
        $arguments = $toolCall->function->getArgumentsAsArray();

        return call_user_func($handler, $arguments);
    }

    /**
     * Check if a tool is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Get a tool by name.
     */
    public function get(string $name): ?array
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Get the number of registered tools.
     */
    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * Clear all registered tools.
     */
    public function clear(): self
    {
        $this->tools = [];

        return $this;
    }
}
