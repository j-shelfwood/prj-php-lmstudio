<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Container;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Http\Client;
use Shelfwood\LMStudio\LMS;
use Shelfwood\LMStudio\OpenAI;

/**
 * A simple service container for managing dependencies.
 */
class ServiceContainer implements ContainerInterface
{
    /**
     * The registered services.
     *
     * @var array<string, callable>
     */
    protected array $services = [];

    /**
     * The resolved instances.
     *
     * @var array<string, mixed>
     */
    protected array $resolved = [];

    /**
     * Create a new service container instance.
     */
    public function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * Register the default services.
     */
    protected function registerDefaults(): void
    {
        // Register the config
        $this->bind(LMStudioConfig::class, function () {
            return new LMStudioConfig;
        });

        // Register the HTTP client
        $this->bind(Client::class, function () {
            return new Client(
                $this->get(LMStudioConfig::class),
                $this->has(LoggerInterface::class) ? $this->get(LoggerInterface::class) : null
            );
        });

        // Register the LMS client
        $this->bind(LMS::class, function () {
            return new LMS($this->get(LMStudioConfig::class));
        });

        // Register the OpenAI client
        $this->bind(OpenAI::class, function () {
            return new OpenAI($this->get(LMStudioConfig::class));
        });

        // Register the default client (LMS)
        $this->bind(LMStudioClientInterface::class, function () {
            return $this->get(LMS::class);
        });

        // Register the request factory
        $this->bind(\Shelfwood\LMStudio\Http\Factories\RequestFactoryInterface::class, function () {
            return new \Shelfwood\LMStudio\Http\Factories\RequestFactory(
                $this->get(LMStudioConfig::class)
            );
        });

        // Register the conversation manager
        $this->bind(\Shelfwood\LMStudio\Conversations\ConversationManagerInterface::class, function () {
            return new \Shelfwood\LMStudio\Conversations\ConversationManager(
                $this->get(LMStudioClientInterface::class),
                $this->get(\Shelfwood\LMStudio\Http\Factories\RequestFactoryInterface::class)
            );
        });

        // Register the tool registry
        $this->bind(\Shelfwood\LMStudio\Tools\ToolRegistryInterface::class, function () {
            return new \Shelfwood\LMStudio\Tools\ToolRegistry;
        });

        // Register the stream builder factory
        $this->bind(\Shelfwood\LMStudio\Streaming\StreamBuilderInterface::class, function () {
            return new \Shelfwood\LMStudio\Streaming\StreamBuilder(
                $this->get(LMStudioClientInterface::class),
                $this->get(\Shelfwood\LMStudio\Http\Factories\RequestFactoryInterface::class)
            );
        });
    }

    /**
     * Bind a service to the container.
     *
     * @param  string  $id  The service identifier
     * @param  callable  $concrete  The service factory
     */
    public function bind(string $id, callable $concrete): self
    {
        $this->services[$id] = $concrete;
        unset($this->resolved[$id]);

        return $this;
    }

    /**
     * Determine if a service is registered.
     *
     * @param  string  $id  The service identifier
     */
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    /**
     * Get a service from the container.
     *
     * @param  string  $id  The service identifier
     *
     * @throws \Psr\Container\NotFoundExceptionInterface No entry was found for **this** identifier.
     * @throws \Psr\Container\ContainerExceptionInterface Error while retrieving the entry.
     */
    public function get(string $id): mixed
    {
        if (! $this->has($id)) {
            throw new NotFoundException("Service not found: {$id}");
        }

        if (! isset($this->resolved[$id])) {
            $this->resolved[$id] = call_user_func($this->services[$id]);
        }

        return $this->resolved[$id];
    }

    /**
     * Set a service instance.
     *
     * @param  string  $id  The service identifier
     * @param  mixed  $instance  The service instance
     */
    public function instance(string $id, mixed $instance): self
    {
        $this->resolved[$id] = $instance;
        $this->services[$id] = fn () => $instance;

        return $this;
    }

    /**
     * Set the configuration.
     *
     * @param  LMStudioConfig  $config  The configuration
     */
    public function withConfig(LMStudioConfig $config): self
    {
        $this->instance(LMStudioConfig::class, $config);

        // Reset clients when config changes
        unset($this->resolved[Client::class]);
        unset($this->resolved[LMS::class]);
        unset($this->resolved[OpenAI::class]);

        return $this;
    }

    /**
     * Set the logger.
     *
     * @param  LoggerInterface  $logger  The logger
     */
    public function withLogger(LoggerInterface $logger): self
    {
        $this->instance(LoggerInterface::class, $logger);

        // Reset HTTP client when logger changes
        unset($this->resolved[Client::class]);

        return $this;
    }

    /**
     * Set the default client.
     *
     * @param  string  $clientClass  The client class (LMS::class or OpenAI::class)
     */
    public function useClient(string $clientClass): self
    {
        if (! in_array($clientClass, [LMS::class, OpenAI::class], true)) {
            throw new \InvalidArgumentException('Invalid client class. Must be LMS::class or OpenAI::class.');
        }

        $this->bind(LMStudioClientInterface::class, function () use ($clientClass) {
            return $this->get($clientClass);
        });

        return $this;
    }
}
