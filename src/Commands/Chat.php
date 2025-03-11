<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Commands;

use Shelfwood\LMStudio\Commands\Traits\ToolAwareCommand;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Conversations\Conversation;
use Shelfwood\LMStudio\Conversations\ConversationManager;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\Message;
use Shelfwood\LMStudio\ValueObjects\Tool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'chat',
    description: 'Interactive chat with a language model'
)]
class Chat extends BaseCommand
{
    use ToolAwareCommand;

    protected Conversation $conversation;

    /**
     * Configures the command
     */
    protected function configure(): void
    {
        $this->configureCommonOptions();

        $this
            ->addOption(
                'system',
                's',
                InputOption::VALUE_OPTIONAL,
                'Initial system message to set the assistant\'s behavior',
                'You are a helpful assistant.'
            )
            ->addOption(
                'temperature',
                't',
                InputOption::VALUE_OPTIONAL,
                'Temperature for response generation (0.0 to 2.0)',
                '0.7'
            )
            ->addOption(
                'max-tokens',
                null,
                InputOption::VALUE_OPTIONAL,
                'Maximum number of tokens to generate',
                '4000'
            )
            ->addOption(
                'tools',
                null,
                InputOption::VALUE_NONE,
                'Enable example tools (calculator and weather)'
            );
    }

    /**
     * Executes the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $systemMessage = $input->getOption('system');
        $api = $input->getOption('api');
        $temperature = (float) $input->getOption('temperature');
        $maxTokens = (int) $input->getOption('max-tokens');
        $enableTools = $input->getOption('tools');

        // Get the client
        $client = $this->getClient($input, $io);

        if ($client === null) {
            return Command::FAILURE;
        }

        // Get the model
        $model = $this->getModel($input, $client, $io);

        if ($model === null) {
            return Command::FAILURE;
        }

        // Initialize tools if enabled
        $toolRegistry = null;

        if ($enableTools) {
            $toolRegistry = $this->createToolRegistry();
            $this->registerCommonTools($toolRegistry);
            $io->note('Tools enabled: calculator, weather, date');
        }

        // Create a conversation manager
        $conversationManager = new ConversationManager($client);

        // Initialize conversation
        if ($toolRegistry !== null) {
            $this->conversation = $conversationManager->createConversationWithTools(
                $toolRegistry,
                'CLI Chat',
                $systemMessage
            );
        } else {
            $this->conversation = $conversationManager->createConversationWithSystem(
                $systemMessage,
                'CLI Chat'
            );
        }

        // Set model, temperature, and max tokens
        $this->conversation->setModel($model);
        $this->conversation->setTemperature($temperature);
        $this->conversation->setMaxTokens($maxTokens);

        // Store metadata
        $this->conversation->setMetadata([
            'api' => $api,
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'tools_enabled' => $enableTools,
        ]);

        $this->toolRegistry = $toolRegistry;

        // Display welcome message
        $io->title('LMStudio CLI Chat');
        $io->text([
            "Model: {$model}",
            "API: {$api}",
            "System message: {$systemMessage}",
            'Type your message and press Enter. Type /exit to quit, /help for more commands.',
        ]);

        // Start the chat loop
        $this->startChatLoop($io, $input, $output, $client, $model, $temperature, $maxTokens);

        return Command::SUCCESS;
    }

    /**
     * Starts the interactive chat loop
     */
    private function startChatLoop(
        SymfonyStyle $io,
        InputInterface $input,
        OutputInterface $output,
        LMStudioClientInterface $client,
        string $model,
        float $temperature,
        int $maxTokens
    ): void {
        $exitCommands = ['/exit', '/quit', '/q'];
        $clearCommand = '/clear';
        $helpCommand = '/help';
        $disableToolsCommand = '/no-tools';
        $enableToolsCommand = '/tools';
        $saveCommand = '/save';
        $loadCommand = '/load';
        $streamingOnCommand = '/streaming on';
        $streamingOffCommand = '/streaming off';

        $io->writeln('<info>Type your message and press Enter to chat with the model.</info>');
        $io->writeln('<info>Type /exit, /quit, or /q to end the chat.</info>');
        $io->writeln('<info>Type /clear to clear the chat history.</info>');
        $io->writeln('<info>Type /tools to enable tools (if disabled).</info>');
        $io->writeln('<info>Type /no-tools to disable tools.</info>');
        $io->writeln('<info>Type /save <filename> to save the conversation.</info>');
        $io->writeln('<info>Type /load <filename> to load a conversation.</info>');
        $io->writeln('<info>Type /streaming on|off to toggle streaming mode.</info>');
        $io->writeln('<info>Type /help to see available commands.</info>');
        $io->newLine();

        $useTools = $this->toolRegistry !== null;
        $useStreaming = true;

        while (true) {
            $userInput = $io->ask('🧑‍💻 <fg=blue>You:</> ');

            // Check for exit command
            if (in_array($userInput, $exitCommands, true)) {
                $io->writeln('<info>Ending chat session. Goodbye!</info>');

                break;
            }

            // Check for clear command
            if ($userInput === $clearCommand) {
                // Create a new conversation with the builder
                $conversationManager = new ConversationManager($client);

                if ($useTools && $this->toolRegistry) {
                    $this->conversation = $conversationManager->createConversationWithTools(
                        $this->toolRegistry,
                        'CLI Chat',
                        $input->getOption('system')
                    );
                } else {
                    $this->conversation = $conversationManager->createConversationWithSystem(
                        $input->getOption('system'),
                        'CLI Chat'
                    );
                }

                // Set model, temperature, and max tokens
                $this->conversation->setModel($model);
                $this->conversation->setTemperature($temperature);
                $this->conversation->setMaxTokens($maxTokens);

                $io->writeln('<info>Chat history cleared. Only the system message remains.</info>');

                continue;
            }

            // Check for help command
            if ($userInput === $helpCommand) {
                $this->displayHelp($io);

                continue;
            }

            // Check for disable tools command
            if ($userInput === $disableToolsCommand) {
                $useTools = false;
                $this->conversation->setToolRegistry(new ToolRegistry);
                $io->writeln('<info>Tools disabled for this session.</info>');

                continue;
            }

            // Check for enable tools command
            if ($userInput === $enableToolsCommand) {
                if ($this->toolRegistry === null) {
                    try {
                        $this->toolRegistry = $this->createToolRegistry();
                        $useTools = true;
                        $this->conversation->setToolRegistry($this->toolRegistry);
                        $io->writeln('<info>Tools enabled for this session.</info>');
                    } catch (\Exception $e) {
                        $io->error('Failed to initialize tools: '.$e->getMessage());
                    }
                } else {
                    $useTools = true;
                    $this->conversation->setToolRegistry($this->toolRegistry);
                    $io->writeln('<info>Tools enabled for this session.</info>');
                }

                continue;
            }

            // Check for streaming on command
            if ($userInput === $streamingOnCommand) {
                $useStreaming = true;
                $io->writeln('<info>Streaming mode enabled.</info>');

                continue;
            }

            // Check for streaming off command
            if ($userInput === $streamingOffCommand) {
                $useStreaming = false;
                $io->writeln('<info>Streaming mode disabled.</info>');

                continue;
            }

            // Check for save command
            if (strpos($userInput, $saveCommand) === 0) {
                $parts = explode(' ', $userInput, 2);
                $filename = $parts[1] ?? 'conversation_'.time().'.json';

                try {
                    $json = $this->conversation->toJson();
                    file_put_contents($filename, $json);
                    $io->writeln("<info>Conversation saved to {$filename}</info>");
                } catch (\Exception $e) {
                    $io->error('Failed to save conversation: '.$e->getMessage());
                }

                continue;
            }

            // Check for load command
            if (strpos($userInput, $loadCommand) === 0) {
                $parts = explode(' ', $userInput, 2);
                $filename = $parts[1] ?? null;

                if (! $filename) {
                    $io->error('Please specify a filename: /load <filename>');

                    continue;
                }

                if (! file_exists($filename)) {
                    $io->error("File not found: {$filename}");

                    continue;
                }

                try {
                    $json = file_get_contents($filename);
                    $conversationManager = new ConversationManager($client);
                    $this->conversation = $conversationManager->loadConversation($json);
                    $io->writeln("<info>Conversation loaded from {$filename}</info>");

                    // Display the loaded conversation
                    $io->section('Loaded Conversation:');

                    foreach ($this->conversation->getHistory()->getMessages() as $message) {
                        if ($message->role === 'system') {
                            $io->writeln('🔧 <fg=yellow>System:</> '.$message->content);
                        } elseif ($message->role === 'user') {
                            $io->writeln('🧑‍💻 <fg=blue>User:</> '.$message->content);
                        } elseif ($message->role === 'assistant') {
                            $io->writeln('🤖 <fg=green>Assistant:</> '.$message->content);
                        }
                    }
                } catch (\Exception $e) {
                    $io->error('Failed to load conversation: '.$e->getMessage());
                }

                continue;
            }

            // Add user message to conversation
            $this->conversation->addUserMessage($userInput);

            $io->write('🤖 <fg=green>Assistant:</> ');

            try {
                if ($useStreaming) {
                    // Stream the response
                    $this->conversation->streamResponse(function ($chunk) use ($io): void {
                        if ($chunk->hasContent()) {
                            $io->write($chunk->getContent());
                        }
                    });
                } else {
                    // Get a non-streaming response
                    $response = $this->conversation->getResponse();
                    $io->write($response);
                }
            } catch (\Exception $e) {
                $io->newLine();
                $io->error('Error: '.$e->getMessage());

                // If we get an error and tools are enabled, try disabling them
                if ($useTools && $this->toolRegistry) {
                    $useTools = false;
                    $this->conversation->setToolRegistry(new ToolRegistry);
                    $io->warning('Disabling tools due to API error. You can re-enable them with /tools');
                }
            }

            $io->newLine();
        }
    }

    /**
     * Creates a tool registry with example tools
     */
    private function createToolRegistry(): ToolRegistry
    {
        $registry = new ToolRegistry;
        $this->registerCommonTools($registry);

        return $registry;
    }

    /**
     * Displays help information
     */
    private function displayHelp(SymfonyStyle $io): void
    {
        $io->section('Available Commands');
        $io->listing([
            '/exit, /quit, /q - End the chat session',
            '/clear - Clear the chat history',
            '/tools - Enable tools',
            '/no-tools - Disable tools',
            '/streaming on|off - Enable or disable streaming mode',
            '/save <filename> - Save the conversation to a file',
            '/load <filename> - Load a conversation from a file',
            '/help - Display this help message',
        ]);
    }
}
