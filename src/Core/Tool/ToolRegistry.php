<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Tool;

use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;

// Keep for potential use in parameter validation

/**
 * Registry for managing tool registrations.
 */
class ToolRegistry
{
    /**
     * @var array<string, callable(array<string, mixed>): mixed> Map of tool names to callbacks
     */
    private array $callbacks = [];

    /**
     * @var array<string, Tool> Map of tool names to Tool objects
     */
    private array $tools = [];

    /**
     * Register a tool with the registry.
     *
     * @param  string  $name  The name of the tool
     * @param  callable(array<string, mixed>): mixed  $callback  The callback to execute when the tool is called
     * @param  array<string, mixed>  $parameters  The function parameters schema (array, not ToolParameters object directly)
     * @param  string|null  $description  The description of the tool
     * @return $this
     */
    public function registerTool(string $name, callable $callback, array $parameters, ?string $description = null): self
    {
        $this->callbacks[$name] = $callback;

        // Validate and create ToolParameters object from the input array
        try {
            // Pass the original parameters array to fromArray
            $toolParameters = ToolParameters::fromArray($parameters);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException("Invalid parameters definition for tool '{$name}': ".$e->getMessage(), 0, $e);
        }

        // Create the ToolDefinition
        $toolDefinition = new ToolDefinition(
            name: $name,
            description: $description ?? '',
            parameters: $toolParameters
        );

        // Create and store the Tool object
        $this->tools[$name] = new Tool(
            ToolType::FUNCTION,
            $toolDefinition
        );

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
        return isset($this->callbacks[$name]); // Check callback existence, implies tool existence
    }

    /**
     * Get a tool callback.
     *
     * @param  string  $name  The name of the tool
     * @return (callable(array<string, mixed>): mixed)|null The tool callback, or null if not found
     */
    public function getCallback(string $name): ?callable
    {
        return $this->callbacks[$name] ?? null;
    }

    /**
     * Execute a tool by name.
     *
     * @param  string  $name  The name of the tool
     * @param  array<string, mixed>  $arguments  The arguments to pass to the tool
     * @return mixed The result of the tool execution
     *
     * @throws \RuntimeException If the tool is not found
     */
    public function executeTool(string $name, array $arguments)
    {
        if (! isset($this->callbacks[$name])) {
            throw new \RuntimeException("Tool '{$name}' not found");
        }

        return ($this->callbacks[$name])($arguments);
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
     * Get all registered tools as a list of Tool objects.
     *
     * @return list<Tool> // <-- Correct return type hint
     */
    public function getTools(): array // Renamed from getToolsAsArray, simplified implementation
    {
        return array_values($this->tools);
    }
}
