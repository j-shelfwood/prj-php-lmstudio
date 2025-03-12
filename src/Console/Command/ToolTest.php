<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Console\Command;

use Shelfwood\LMStudio\Console\Command\Trait\ToolAwareCommand;
use Shelfwood\LMStudio\Chat\Conversation;
use Shelfwood\LMStudio\Tool\ToolRegistry;
use Shelfwood\LMStudio\ValueObject\FunctionCall;
use Shelfwood\LMStudio\ValueObject\Message;
use Shelfwood\LMStudio\ValueObject\Tool;
use Shelfwood\LMStudio\ValueObject\ToolCall;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'tool:test',
    description: 'Test tool functionality with predefined prompts'
)]
class ToolTest extends BaseCommand
{
    use ToolAwareCommand;

    protected ?ToolRegistry $toolRegistry = null;

    protected ?InputInterface $input = null;

    /**
     * Configure the command.
     */
    protected function configure(): void
    {
        $this->configureCommonOptions();

        $this
            ->addOption(
                'tool',
                't',
                InputOption::VALUE_OPTIONAL,
                'Specific tool to test (calculator, weather, date, or all)',
                'all'
            )
            ->addOption(
                'streaming',
                's',
                InputOption::VALUE_NONE,
                'Use streaming mode for responses'
            )
            ->addOption(
                'prompt',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Custom prompt to test with tools'
            )
            ->addOption(
                'model',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Specific model to use for testing'
            );
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $io = new SymfonyStyle($input, $output);
        $io->title('Tool Testing Command');

        // Get the client
        $client = $this->getClient($input, $io);

        if (! $client) {
            return Command::FAILURE;
        }

        // Get the model - default to qwen2.5-7b-instruct-1m if not specified
        $model = $input->getOption('model') ?: 'qwen2.5-7b-instruct-1m';
        $io->note("Using model: {$model}");

        // Display client info
        $this->displayClientInfo($io, $client, $model, $input->getOption('api'));

        // Create tool registry
        $this->toolRegistry = new ToolRegistry;
        $this->registerCommonTools($this->toolRegistry, $input->getOption('tool'));

        // Create conversation using the builder
        $conversation = Conversation::builder($client)
            ->withTitle('Tool Test')
            ->withModel($model)
            ->withToolRegistry($this->toolRegistry)
            ->withSystemMessage(
                'You are a helpful assistant that can use tools to provide accurate information. '.
                'ALWAYS use the available tools when appropriate. '.
                'For calculations, ALWAYS use the calculator tool. '.
                'For weather information, ALWAYS use the weather tool. '.
                'For date and time information, ALWAYS use the date tool. '.
                'Do not try to compute these things yourself.'
            )
            ->build();

        // Use custom prompt or predefined prompts
        if ($customPrompt = $input->getOption('prompt')) {
            $this->runCustomPrompt($io, $conversation, $customPrompt, $input->getOption('streaming'));
        } else {
            $this->runPredefinedTests($io, $conversation, $input->getOption('tool'), $input->getOption('streaming'));
        }

        return Command::SUCCESS;
    }

    /**
     * Run a custom prompt with tools.
     */
    private function runCustomPrompt(
        SymfonyStyle $io,
        Conversation $conversation,
        string $prompt,
        bool $streaming
    ): void {
        $io->section('Running custom prompt with tools');
        $io->text("Prompt: {$prompt}");

        // Show available tools
        $this->displayAvailableTools($io, $this->toolRegistry);

        $conversation->addUserMessage($prompt);

        // Get the raw API response instead of using the conversation's getResponse method
        $options = [
            'model' => 'qwen2.5-7b-instruct-1m', // Use the model directly
            'temperature' => 0.7,
            'max_tokens' => 4000,
        ];

        if ($this->toolRegistry) {
            $options['tools'] = array_map(
                fn ($tool) => $tool->jsonSerialize(),
                $this->toolRegistry->getTools()
            );
            $options['tool_choice'] = 'auto';
        }

        // Get the client from the execute method
        $client = $this->getClient($this->input, $io);
        $response = $client->chat($conversation->getHistory()->jsonSerialize(), $options);

        $io->section('API Response');
        $io->writeln(json_encode($response, JSON_PRETTY_PRINT));

        // Extract content and tool calls
        if (is_array($response)) {
            $content = $response['choices'][0]['message']['content'] ?? '';
        } else {
            $content = $response->choices[0]->message->content ?? '';
        }

        // Display the response content
        $io->section('Response Content');

        if (! empty($content)) {
            $io->writeln($content);
        } else {
            $io->writeln('<comment>No text content in response</comment>');
        }

        // Process tool calls
        $toolCalls = $this->extractToolCalls($response);

        if ($toolCalls) {
            $io->section('Tool Calls');

            foreach ($toolCalls as $index => $toolCallData) {
                $name = is_array($toolCallData)
                    ? ($toolCallData['function']['name'] ?? '')
                    : ($toolCallData->function->name ?? '');

                $id = is_array($toolCallData)
                    ? ($toolCallData['id'] ?? '')
                    : ($toolCallData->id ?? '');

                $type = is_array($toolCallData)
                    ? ($toolCallData['type'] ?? 'function')
                    : ($toolCallData->type ?? 'function');

                $arguments = is_array($toolCallData)
                    ? ($toolCallData['function']['arguments'] ?? '{}')
                    : ($toolCallData->function->arguments ?? '{}');

                $io->writeln('Tool Call #'.($index + 1).':');
                $io->writeln("  ID: {$id}");
                $io->writeln("  Type: {$type}");
                $io->writeln("  Tool: {$name}");
                $io->writeln("  Arguments: {$arguments}");

                // Execute the tool if available
                if ($this->toolRegistry && $this->toolRegistry->has($name)) {
                    $result = $this->executeToolCall($this->toolRegistry, $toolCallData);

                    if ($result) {
                        $io->writeln("  Result: {$result}");

                        // Add the tool response to the conversation
                        $conversation->addToolMessage($result, $id);
                    }
                } else {
                    $io->warning("Tool '{$name}' not found in registry");
                }

                $io->newLine();
            }

            // Get a follow-up response if we processed tool calls
            $io->section('Follow-up Response');
            $followUpResponse = $conversation->getResponse();
            $io->writeln($followUpResponse);
        } else {
            $io->warning('No tool calls were made by the model');
        }
    }

    /**
     * Run predefined tests for tools.
     */
    private function runPredefinedTests(
        SymfonyStyle $io,
        Conversation $conversation,
        string $toolOption,
        bool $streaming
    ): void {
        $prompts = $this->getPredefinedPrompts($toolOption);

        foreach ($prompts as $description => $prompt) {
            $io->section("Test: {$description}");
            $io->text("Prompt: {$prompt}");

            // Clear previous messages except system message
            $systemMessage = null;

            foreach ($conversation->getMessages() as $message) {
                if ($message->role === \Shelfwood\LMStudio\Enums\Role::SYSTEM) {
                    $systemMessage = $message->content;

                    break;
                }
            }

            // Create a new conversation for each test
            $client = $this->getClient($this->input, $io);

            // Build the conversation using the builder
            $conversationBuilder = Conversation::builder($client)
                ->withTitle("Tool Test: {$description}")
                ->withModel('qwen2.5-7b-instruct-1m')
                ->withToolRegistry($this->toolRegistry);

            // Add system message
            if ($systemMessage) {
                $conversationBuilder = $conversationBuilder->withSystemMessage($systemMessage);
            } else {
                $conversationBuilder = $conversationBuilder->withSystemMessage(
                    'You are a helpful assistant that can use tools to provide accurate information. '.
                    'ALWAYS use the available tools when appropriate. '.
                    'For calculations, ALWAYS use the calculator tool. '.
                    'For weather information, ALWAYS use the weather tool. '.
                    'For date and time information, ALWAYS use the date tool. '.
                    'Do not try to compute these things yourself.'
                );
            }

            $conversation = $conversationBuilder->build();

            // Add user message
            $conversation->addUserMessage($prompt);

            // Get the raw API response
            $options = [
                'model' => 'qwen2.5-7b-instruct-1m',
                'temperature' => 0.7,
                'max_tokens' => 4000,
            ];

            if ($this->toolRegistry) {
                $options['tools'] = array_map(
                    fn ($tool) => $tool->jsonSerialize(),
                    $this->toolRegistry->getTools()
                );
                $options['tool_choice'] = 'auto';
            }

            $response = $client->chat($conversation->getHistory()->jsonSerialize(), $options);

            // Extract content and tool calls
            if (is_array($response)) {
                $content = $response['choices'][0]['message']['content'] ?? '';
                $toolCalls = $response['choices'][0]['message']['toolCalls'] ?? null;

                // Check for camelCase property names (common in some API responses)
                if (! $toolCalls && isset($response['choices'][0]['message']['toolCalls'])) {
                    $toolCalls = $response['choices'][0]['message']['toolCalls'];
                }
            } else {
                $content = $response->choices[0]->message->content ?? '';
                $toolCalls = $response->choices[0]->message->tool_calls ?? null;

                // Check for camelCase property names
                if (! $toolCalls && isset($response->choices[0]->message->toolCalls)) {
                    $toolCalls = $response->choices[0]->message->toolCalls;
                }
            }

            // Display the response content
            $io->writeln('');
            $io->writeln('<info>Response:</info>');

            if (! empty($content)) {
                $io->writeln($content);
            } else {
                $io->writeln('<comment>No text content in response</comment>');
            }

            // Process tool calls
            if ($toolCalls) {
                $io->writeln('');
                $io->writeln('<info>Tool Calls:</info>');

                foreach ($toolCalls as $index => $toolCallData) {
                    $name = is_array($toolCallData)
                        ? ($toolCallData['function']['name'] ?? '')
                        : ($toolCallData->function->name ?? '');

                    $id = is_array($toolCallData)
                        ? ($toolCallData['id'] ?? '')
                        : ($toolCallData->id ?? '');

                    $type = is_array($toolCallData)
                        ? ($toolCallData['type'] ?? 'function')
                        : ($toolCallData->type ?? 'function');

                    $arguments = is_array($toolCallData)
                        ? ($toolCallData['function']['arguments'] ?? '{}')
                        : ($toolCallData->function->arguments ?? '{}');

                    $io->writeln('Tool Call #'.($index + 1).':');
                    $io->writeln("  Tool: {$name}");
                    $io->writeln("  Arguments: {$arguments}");

                    // Execute the tool if available
                    if ($this->toolRegistry && $this->toolRegistry->has($name)) {
                        try {
                            // Create a ToolCall object
                            $toolCall = new ToolCall(
                                $id,
                                $type,
                                new FunctionCall(
                                    $name,
                                    $arguments
                                )
                            );

                            // Execute the tool
                            $result = $this->toolRegistry->execute($toolCall);
                            $io->writeln("  Result: {$result}");

                            // Add the tool response to the conversation
                            $conversation->addToolMessage($result, $id);
                        } catch (\Exception $e) {
                            $io->error("Error executing tool: {$e->getMessage()}");
                        }
                    }
                }

                // Get a follow-up response if we processed tool calls
                $io->writeln('');
                $io->writeln('<info>Follow-up Response:</info>');
                $followUpResponse = $conversation->getResponse();
                $io->writeln($followUpResponse);
            }

            $io->newLine();
        }
    }

    /**
     * Get predefined prompts based on the selected tool option.
     *
     * @return array<string, string>
     */
    private function getPredefinedPrompts(string $toolOption): array
    {
        $prompts = [];

        if ($toolOption === 'all' || $toolOption === 'calculator') {
            $prompts['Basic calculation'] = 'What is 125 * 37?';
            $prompts['Complex calculation'] = 'If I have 3 apples and you have 5 apples, and we each eat 2 apples, how many do we have left in total?';
        }

        if ($toolOption === 'all' || $toolOption === 'weather') {
            $prompts['Weather lookup'] = 'What\'s the weather like in Amsterdam?';
            $prompts['Weather comparison'] = 'Compare the weather in New York and London.';
        }

        if ($toolOption === 'all' || $toolOption === 'date') {
            $prompts['Current date'] = 'What is today\'s date?';
            $prompts['Time zones'] = 'What time is it in Asia/Tokyo right now?';
        }

        if ($toolOption === 'all') {
            $prompts['Multi-tool test'] = 'I need to know if I should bring an umbrella to my meeting in London at 3pm. Also, what is 15% of 85?';
        }

        return $prompts;
    }
}
