<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Tools;

use Shelfwood\LMStudio\Exceptions\InvalidToolDefinitionException;
use Shelfwood\LMStudio\Exceptions\ToolExecutionException;
use Shelfwood\LMStudio\ValueObjects\Tool;
use Shelfwood\LMStudio\ValueObjects\ToolCall;

/**
 * Registry for tools and their execution handlers.
 *
 * This class manages the registration and execution of tools that can be
 * used by the LM Studio API. Tools are functions that can be called by
 * the model during a conversation.
 *
 * Example usage:
 * ```php
 * // Register a single tool
 * $registry = new ToolRegistry();
 * $registry->register(Tool::function('get_weather', 'Get the weather for a location', [
 *     'location' => ['type' => 'string', 'description' => 'The location to get weather for'],
 * ]), function (array $args): string {
 *     return "The weather in {$args['location']} is sunny.";
 * });
 *
 * // Register multiple tools
 * $registry->registerMany([
 *     [
 *         'tool' => Tool::function('get_time', 'Get the current time', []),
 *         'handler' => fn (): string => 'The current time is ' . date('H:i:s'),
 *     ],
 *     [
 *         'tool' => Tool::function('get_date', 'Get the current date', []),
 *         'handler' => fn (): string => 'Today is ' . date('Y-m-d'),
 *     ],
 * ]);
 *
 * // Execute a tool
 * $result = $registry->execute($toolCall);
 * ```
 */
class ToolRegistry implements \Countable, ToolRegistryInterface
{
    /**
     * @var array<string, array{tool: Tool, handler: callable}>
     */
    private array $tools = [];

    /**
     * @var array<string, callable|null>
     */
    private array $progressCallbacks = [];

    /**
     * Register a tool with its execution handler.
     *
     * @param  Tool  $tool  The tool to register
     * @param  callable  $handler  The function to execute when the tool is called
     * @return self For method chaining
     *
     * @throws InvalidToolDefinitionException If the tool is invalid
     */
    public function register(Tool $tool, callable $handler): self
    {
        if (empty($tool->function->name)) {
            throw new InvalidToolDefinitionException('Tool function name cannot be empty');
        }

        if (isset($this->tools[$tool->function->name])) {
            throw new InvalidToolDefinitionException("Tool '{$tool->function->name}' is already registered");
        }

        $this->tools[$tool->function->name] = [
            'tool' => $tool,
            'handler' => $handler,
        ];

        return $this;
    }

    /**
     * Register multiple tools at once.
     *
     * @param  array<array{tool: Tool, handler: callable}>  $tools  Array of tools and handlers to register
     * @return self For method chaining
     *
     * @throws InvalidToolDefinitionException If any tool is invalid
     */
    public function registerMany(array $tools): self
    {
        foreach ($tools as $item) {
            if (! isset($item['tool']) || ! isset($item['handler'])) {
                throw new InvalidToolDefinitionException('Each tool registration must include both "tool" and "handler" keys');
            }

            $this->register($item['tool'], $item['handler']);
        }

        return $this;
    }

    /**
     * Register a function tool with its execution handler.
     *
     * @param  string  $name  The name of the function
     * @param  string  $description  The description of the function
     * @param  array<string, mixed>  $parameters  The parameters of the function (JSON Schema)
     * @param  callable  $handler  The function to execute when the tool is called
     * @return self For method chaining
     *
     * @throws InvalidToolDefinitionException If the tool is invalid
     */
    public function registerFunction(string $name, string $description, array $parameters, callable $handler): self
    {
        if (empty($name)) {
            throw new InvalidToolDefinitionException('Tool function name cannot be empty');
        }

        if (empty($description)) {
            throw new InvalidToolDefinitionException('Tool function description cannot be empty');
        }

        return $this->register(Tool::function($name, $description, $parameters), $handler);
    }

    /**
     * Get all registered tools.
     *
     * @return array<Tool> The registered tools
     */
    public function getTools(): array
    {
        return array_values(array_map(fn ($item) => $item['tool'], $this->tools));
    }

    /**
     * Set a progress callback for a tool.
     */
    public function setProgressCallback(string $toolName, ?callable $callback): self
    {
        $this->progressCallbacks[$toolName] = $callback;

        return $this;
    }

    /**
     * Execute a tool call and return a standardized response.
     */
    public function executeWithResponse(ToolCall $toolCall): ToolResponse
    {
        $name = $toolCall->function->name;

        try {
            $result = $this->execute($toolCall);

            return ToolResponse::success(
                $toolCall->id,
                $name,
                (string) $result
            );
        } catch (\Throwable $e) {
            return ToolResponse::error(
                $toolCall->id,
                $name,
                $e->getMessage()
            );
        }
    }

    /**
     * Execute a tool call.
     *
     * @param  ToolCall  $toolCall  The tool call to execute
     * @return string The result of the tool execution
     *
     * @throws ToolExecutionException If the tool execution fails or is not registered
     */
    public function execute(ToolCall $toolCall): string
    {
        $name = $toolCall->function->name;

        if (! isset($this->tools[$name])) {
            throw new ToolExecutionException("Tool '{$name}' is not registered");
        }

        try {
            $handler = $this->tools[$name]['handler'];
            $arguments = $toolCall->function->getArgumentsAsArray();

            // Create a progress reporter if we have a progress callback
            $progressReporter = null;

            if (isset($this->progressCallbacks[$name])) {
                $progressReporter = function (int $progress, string $message = '') use ($toolCall, $name): void {
                    $response = ToolResponse::progress(
                        $toolCall->id,
                        $name,
                        $progress,
                        $message
                    );

                    if ($this->progressCallbacks[$name] !== null) {
                        call_user_func($this->progressCallbacks[$name], $response);
                    }
                };
            }

            // Add the progress reporter to the arguments if the handler accepts it
            $reflection = new \ReflectionFunction($handler);
            $parameters = $reflection->getParameters();

            if (count($parameters) > 1 && $parameters[1]->getName() === 'progressReporter') {
                $result = call_user_func($handler, $arguments, $progressReporter);
            } else {
                $result = call_user_func($handler, $arguments);
            }

            return (string) $result;
        } catch (\Throwable $e) {
            if ($e instanceof ToolExecutionException) {
                throw $e;
            }

            throw new ToolExecutionException(
                "Error executing tool '{$name}': {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Check if a tool is registered.
     *
     * @param  string  $name  The name of the tool to check
     * @return bool True if the tool is registered, false otherwise
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Get a tool by name.
     *
     * @param  string  $name  The name of the tool to get
     * @return array{tool: Tool, handler: callable}|null The tool and its handler, or null if not found
     */
    public function get(string $name): ?array
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Get the number of registered tools.
     *
     * @return int The number of registered tools
     */
    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * Clear all registered tools.
     *
     * @return self For method chaining
     */
    public function clear(): self
    {
        $this->tools = [];

        return $this;
    }
}
