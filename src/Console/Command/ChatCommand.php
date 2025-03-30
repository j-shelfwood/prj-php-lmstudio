<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Console\Command;

use Shelfwood\LMStudio\LMStudioFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class ChatCommand extends BaseCommand
{
    private readonly LMStudioFactory $factory;

    public function __construct(LMStudioFactory $factory)
    {
        parent::__construct('chat');

        $this->factory = $factory; // Store factory

        $this->setDescription('Interactive chat with LM Studio API including tool calling support (Non-Streaming)')
            ->addOption(
                'model',
                null,
                InputOption::VALUE_OPTIONAL,
                'The model to use (defaults to config value)'
            )
            ->addOption(
                'system',
                null,
                InputOption::VALUE_OPTIONAL,
                'System message to use (defaults to "You are a helpful assistant.")'
            );
    }

    protected function handle(): int
    {
        $modelId = $this->option('model') ?: getenv('LMSTUDIO_DEFAULT_MODEL') ?: 'qwen2.5-7b-instruct';
        $systemPrompt = $this->option('system') ?: 'You are a helpful assistant.';

        $this->info("Starting interactive chat with model: {$modelId}");
        $this->line('Type \'/quit\' to exit.');

        try {
            // Create a standard (non-streaming) conversation
            $conversation = $this->factory->createConversation($modelId);

            // Register pre-configured tools
            $toolRegistry = $conversation->toolRegistry;
            $configuredTools = $this->factory->toolConfigService->getToolConfigurations();

            foreach ($configuredTools as $name => $config) {
                $toolRegistry->registerTool(
                    $name,
                    $config['callback'],
                    $config['parameters'] ?? [],
                    $config['description'] ?? null
                );
                $this->info("  Registered tool: {$name}");
            }
            $this->newLine();

            // Add initial system message
            $conversation->addSystemMessage($systemPrompt);

            // Chat loop
            while (true) {
                $userInput = $this->ask('<fg=green>You:</>');

                if ($userInput === null || strtolower($userInput) === '/quit') {
                    $this->info('Exiting chat.');

                    break;
                }

                if (empty(trim($userInput))) {
                    continue;
                }

                $conversation->addUserMessage($userInput);

                // Show thinking indicator
                $this->output->write('<fg=yellow>Assistant:</> Thinking...');

                try {
                    // Get response (handles potential tool calls automatically)
                    $responseContent = $conversation->getResponse();

                    // Clear thinking message and show response
                    // Overwrite the "Thinking..." line
                    $this->output->write("\r".str_repeat(' ', strlen('<fg=yellow>Assistant:</> Thinking...'))."\r");
                    $this->line("<fg=yellow>Assistant:</> {$responseContent}");
                } catch (Throwable $e) {
                    // Clear thinking message and show error
                    $this->output->write("\r".str_repeat(' ', strlen('<fg=yellow>Assistant:</> Thinking...'))."\r");
                    $this->error("Assistant Error: {$e->getMessage()}");
                    // Optionally remove the last user message if the call failed?
                    // Or allow the user to retry / continue?
                    // For simplicity, we just report and continue the loop.
                }
            }
        } catch (Throwable $e) {
            $this->error("Failed to initialize chat: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
