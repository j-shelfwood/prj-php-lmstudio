<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Core\Tool;

/**
 * Interface for tools that can be used in a conversation.
 */
interface ToolInterface
{
    /**
     * Get the name of the tool.
     */
    public function getName(): string;

    /**
     * Get the description of the tool.
     */
    public function getDescription(): string;

    /**
     * Get the parameters schema for the tool.
     *
     * @return array The JSON schema for the parameters
     */
    public function getParametersSchema(): array;

    /**
     * Execute the tool with the given arguments.
     *
     * @param  array  $arguments  The arguments to pass to the tool
     * @return mixed The result of executing the tool
     *
     * @throws \Exception If the tool execution fails
     */
    public function execute(array $arguments): mixed;

    /**
     * Convert the tool to an array format compatible with the API.
     *
     * @return array The tool definition in API format
     */
    public function toArray(): array;
}
