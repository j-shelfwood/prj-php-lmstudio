<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Tool;

use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Model\Tool;

/**
 * Registry for managing tool registrations.
 */
class ToolRegistry
{
    /**
     * @var array<string, callable> Map of tool names to callbacks
     */
    private array $callbacks = [];

    /**
     * @var array<string, Tool> Map of tool names to Tool objects
     */
    private array $tools = [];

    /**
     * Register a tool function.
     *
     * @param  string  $name  The name of the function
     * @param  callable  $callback  The function to call
     * @param  array  $parameters  The function parameters schema
     * @param  string|null  $description  The function description
     */
    public function registerTool(string $name, callable $callback, array $parameters, ?string $description = null): self
    {
        $this->callbacks[$name] = $callback;

        $functionDefinition = [
            'name' => $name,
            'parameters' => $parameters,
        ];

        if ($description !== null) {
            $functionDefinition['description'] = $description;
        }

        $this->tools[$name] = new Tool(ToolType::FUNCTION, $functionDefinition);

        return $this;
    }

    /**
     * Check if a tool is registered.
     *
     * @param  string  $name  The name of the tool
     * @return bool Whether the tool is registered
     */
    public function hasTool(string $name): bool
    {
        return isset($this->callbacks[$name]);
    }

    /**
     * Get a tool callback.
     *
     * @param  string  $name  The name of the tool
     * @return callable|null The tool callback, or null if not found
     */
    public function getCallback(string $name): ?callable
    {
        return $this->callbacks[$name] ?? null;
    }

    /**
     * Execute a tool.
     *
     * @param  string  $name  The name of the tool
     * @param  array  $arguments  The arguments to pass to the tool
     * @return mixed The result of the tool execution
     *
     * @throws \InvalidArgumentException If the tool is not registered
     */
    public function executeTool(string $name, array $arguments)
    {
        if (! $this->hasTool($name)) {
            throw new \InvalidArgumentException("Tool '{$name}' is not registered");
        }

        return call_user_func($this->callbacks[$name], $arguments);
    }

    /**
     * Check if any tools are registered.
     *
     * @return bool Whether any tools are registered
     */
    public function hasTools(): bool
    {
        return ! empty($this->tools);
    }

    /**
     * Get all registered tools as an array for API requests.
     *
     * @return array The tools array
     */
    public function getToolsArray(): array
    {
        return array_map(fn (Tool $tool) => $tool->toArray(), $this->tools);
    }

    /**
     * Get all registered tools.
     *
     * @return array<string, Tool> The tools
     */
    public function getTools(): array
    {
        return $this->tools;
    }
}
