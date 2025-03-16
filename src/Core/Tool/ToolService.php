<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Tool;

use Shelfwood\LMStudio\Api\Model\Tool;

/**
 * Service for managing tool registrations and configurations.
 */
class ToolService
{
    private ToolRegistry $toolRegistry;

    /**
     * @var array<string, array> Map of tool configurations
     */
    private array $toolConfigurations;

    public function __construct(ToolRegistry $toolRegistry, array $toolConfigurations = [])
    {
        $this->toolRegistry = $toolRegistry;
        $this->toolConfigurations = $toolConfigurations;
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
     *
     * @param  string  $name  The name of the tool
     * @param  array  $config  The tool configuration
     *
     * @throws \InvalidArgumentException If the configuration is invalid
     */
    private function registerToolFromConfig(string $name, array $config): void
    {
        if (! isset($config['callback']) || ! is_callable($config['callback'])) {
            throw new \InvalidArgumentException("Invalid callback for tool '{$name}'");
        }

        $parameters = $config['parameters'] ?? ['properties' => [], 'required' => []];
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
     * @param  string  $name  The name of the tool
     * @param  callable  $callback  The callback to execute when the tool is called
     * @param  array  $parameters  The function parameters schema
     * @param  string|null  $description  The description of the tool
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

        return $this;
    }

    /**
     * Get the tool registry.
     */
    public function getToolRegistry(): ToolRegistry
    {
        return $this->toolRegistry;
    }

    /**
     * Get all registered tools.
     *
     * @return Tool[]
     */
    public function getTools(): array
    {
        return $this->toolRegistry->getTools();
    }

    /**
     * Get all tool configurations.
     *
     * @return array<string, array>
     */
    public function getToolConfigurations(): array
    {
        return $this->toolConfigurations;
    }
}
