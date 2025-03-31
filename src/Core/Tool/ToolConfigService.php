<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Tool;

use Shelfwood\LMStudio\Core\Event\EventHandler;

/**
 * Service for centralizing tool configuration and providing consistent access to tools.
 */
class ToolConfigService
{
    public readonly ToolRegistry $toolRegistry;

    public readonly ToolExecutor $toolExecutor;

    /** @var array<string, array{callback: callable, parameters: array<string, mixed>, description: string|null}> */
    private array $toolConfigurations = [];

    /**
     * Create a new ToolConfigService instance.
     *
     * @param  ToolRegistry  $toolRegistry  The pre-configured tool registry.
     * @param  ToolExecutor  $toolExecutor  The pre-configured tool executor.
     * @param  array<string, array{callback: callable, parameters: array<string, mixed>, description: string|null}>  $toolConfigurations  Initial array of tool configurations.
     * @param  EventHandler|null  $eventHandler  Optional EventHandler, currently unused here but kept for future consistency.
     */
    public function __construct(
        ToolRegistry $toolRegistry,
        ToolExecutor $toolExecutor,
        array $toolConfigurations = [],
        ?EventHandler $eventHandler = null
    ) {
        $this->toolRegistry = $toolRegistry;
        $this->toolExecutor = $toolExecutor;
        $this->toolConfigurations = $toolConfigurations;

        // Register all configured tools immediately
        $this->registerConfiguredTools();
    }

    /**
     * Register all configured tools.
     */
    public function registerConfiguredTools(): void
    {
        foreach ($this->toolConfigurations as $name => $config) {
            $this->registerToolFromConfig($name, $config);
        }
    }

    /**
     * Register a tool from its configuration.
     */
    private function registerToolFromConfig(string $name, array $config): void
    {
        if (! isset($config['callback']) || ! is_callable($config['callback'])) {
            throw new \InvalidArgumentException("Invalid callback for tool '{$name}'");
        }

        $parameters = $config['parameters'] ?? ['type' => 'object', 'properties' => [], 'required' => []];
        $description = $config['description'] ?? null;

        $this->toolRegistry->registerTool(
            $name,
            $config['callback'],
            $parameters,
            $description
        );
    }

    /**
     * Add a tool configuration.
     *
     * @param  callable(array<string, mixed>): mixed  $callback
     * @param  array<string, mixed>  $parameters
     * @return $this
     */
    public function addToolConfiguration(
        string $name,
        callable $callback,
        array $parameters = [],
        ?string $description = null
    ): self {
        $this->toolConfigurations[$name] = [
            'callback' => $callback,
            'parameters' => $parameters,
            'description' => $description,
        ];

        // Register the tool immediately
        $this->registerToolFromConfig($name, $this->toolConfigurations[$name]);

        return $this;
    }

    /**
     * Get all registered tools as Tool objects.
     *
     * @deprecated Use getToolRegistry()->getTools() directly.
     *
     * @return array<string, \Shelfwood\LMStudio\Api\Model\Tool>
     */
    public function getTools(): array
    {
        return $this->toolRegistry->getTools();
    }

    /**
     * Get all tool configurations.
     *
     * @return array<string, array{callback: callable, parameters: array<string, mixed>, description: string|null}>
     */
    public function getToolConfigurations(): array
    {
        return $this->toolConfigurations;
    }
}
