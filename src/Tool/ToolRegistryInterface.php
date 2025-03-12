<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Tool;

use Shelfwood\LMStudio\ValueObject\Tool;
use Shelfwood\LMStudio\ValueObject\ToolCall;

/**
 * Interface for tool registries.
 */
interface ToolRegistryInterface extends \Countable
{
    /**
     * Register a tool with its execution handler.
     */
    public function register(Tool $tool, callable $handler): self;

    /**
     * Register multiple tools at once.
     */
    public function registerMany(array $tools): self;

    /**
     * Register a function tool with its execution handler.
     */
    public function registerFunction(string $name, string $description, array $parameters, callable $handler): self;

    /**
     * Get all registered tools.
     */
    public function getTools(): array;

    /**
     * Set a progress callback for a tool.
     */
    public function setProgressCallback(string $toolName, ?callable $callback): self;

    /**
     * Execute a tool call.
     */
    public function execute(ToolCall $toolCall): string;

    /**
     * Execute a tool call and return a standardized response.
     */
    public function executeWithResponse(ToolCall $toolCall): ToolResponse;

    /**
     * Check if a tool is registered.
     */
    public function has(string $name): bool;

    /**
     * Get a tool by name.
     */
    public function get(string $name): ?array;

    /**
     * Clear all registered tools.
     */
    public function clear(): self;
}
