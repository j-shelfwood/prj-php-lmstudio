<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Commands\Traits;

use Shelfwood\LMStudio\Enums\ToolType;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\Utilities\ToolCallExtractor;
use Shelfwood\LMStudio\ValueObjects\FunctionCall;
use Shelfwood\LMStudio\ValueObjects\Tool;
use Shelfwood\LMStudio\ValueObjects\ToolCall;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Trait for commands that use tools.
 */
trait ToolAwareCommand
{
    protected ?ToolRegistry $toolRegistry = null;

    /**
     * Register common tools in the registry.
     */
    protected function registerCommonTools(ToolRegistry $registry, string $toolOption = 'all'): void
    {
        if ($toolOption === 'all' || $toolOption === 'calculator') {
            $calculatorTool = Tool::function(
                'calculator',
                'Perform a mathematical calculation',
                [
                    'expression' => [
                        'type' => 'string',
                        'description' => 'The mathematical expression to evaluate',
                        'required' => true,
                    ],
                ]
            );

            $registry->register($calculatorTool, function ($args) {
                try {
                    $expression = $args['expression'] ?? '';
                    $result = eval('return '.$expression.';');

                    return (string) $result;
                } catch (\Throwable $e) {
                    return 'Error: '.$e->getMessage();
                }
            });
        }

        if ($toolOption === 'all' || $toolOption === 'weather') {
            $weatherTool = Tool::function(
                'weather',
                'Get the current weather for a location',
                [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The location to get weather for',
                        'required' => true,
                    ],
                ]
            );

            $registry->register($weatherTool, function ($args) {
                // Simulate weather data
                $location = $args['location'] ?? 'Unknown';
                $conditions = ['sunny', 'cloudy', 'rainy', 'snowy', 'windy'];
                $condition = $conditions[array_rand($conditions)];
                $temperature = rand(0, 35);

                return "Weather for {$location}: {$condition}, {$temperature}°C";
            });
        }

        if ($toolOption === 'all' || $toolOption === 'date') {
            $dateTool = Tool::function(
                'date',
                'Get the current date and time',
                [
                    'timezone' => [
                        'type' => 'string',
                        'description' => 'The timezone to get the date for',
                        'required' => false,
                    ],
                    'format' => [
                        'type' => 'string',
                        'description' => 'The format to display the date in',
                        'required' => false,
                    ],
                ]
            );

            $registry->register($dateTool, function ($args) {
                try {
                    $timezone = $args['timezone'] ?? 'UTC';
                    $format = $args['format'] ?? 'Y-m-d H:i:s';
                    $date = new \DateTime('now', new \DateTimeZone($timezone));

                    return $date->format($format);
                } catch (\Throwable $e) {
                    return 'Error: '.$e->getMessage();
                }
            });
        }
    }

    /**
     * Display available tools.
     */
    protected function displayAvailableTools(SymfonyStyle $io, ToolRegistry $registry): void
    {
        $io->section('Available Tools');
        $tools = $registry->getTools();

        if (empty($tools)) {
            $io->warning('No tools registered');

            return;
        }

        foreach ($tools as $index => $tool) {
            $io->writeln('Tool #'.($index + 1).": {$tool->function->name}");
            $io->writeln("  Description: {$tool->function->description}");
            $io->writeln('  Parameters: '.json_encode($tool->function->parameters, JSON_PRETTY_PRINT));
            $io->newLine();
        }
    }

    /**
     * Extract tool calls from a response.
     *
     * @param  array|object  $response  The API response
     * @return array The extracted tool calls
     *
     * @deprecated Use ToolCallExtractor::extract() instead
     */
    protected function extractToolCalls($response): array
    {
        return ToolCallExtractor::extract($response, false);
    }

    /**
     * Execute a tool call.
     */
    protected function executeToolCall(ToolRegistry $registry, $toolCallData): ?string
    {
        $name = is_array($toolCallData)
            ? ($toolCallData['function']['name'] ?? '')
            : ($toolCallData->function->name ?? '');

        $id = is_array($toolCallData)
            ? ($toolCallData['id'] ?? '')
            : ($toolCallData->id ?? '');

        $typeStr = is_array($toolCallData)
            ? ($toolCallData['type'] ?? 'function')
            : ($toolCallData->type ?? 'function');

        $arguments = is_array($toolCallData)
            ? ($toolCallData['function']['arguments'] ?? '{}')
            : ($toolCallData->function->arguments ?? '{}');

        if ($registry->has($name)) {
            try {
                // Create a ToolCall object
                $toolCall = new ToolCall(
                    $id,
                    ToolType::from($typeStr),
                    new FunctionCall(
                        $name,
                        $arguments
                    )
                );

                // Execute the tool
                return $registry->execute($toolCall);
            } catch (\Exception $e) {
                return 'Error: '.$e->getMessage();
            }
        }

        return null;
    }
}
