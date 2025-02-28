<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Commands;

use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Requests\V0\ChatCompletionRequest as V0ChatCompletionRequest;
use Shelfwood\LMStudio\Requests\V1\ChatCompletionRequest as V1ChatCompletionRequest;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;
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
    protected ChatHistory $history;

    protected string $requestClass;

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
                'stream',
                null,
                InputOption::VALUE_NONE,
                'Stream the response as it\'s generated'
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
        $stream = $input->getOption('stream');

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

        // Set the request class based on the API
        $this->requestClass = $api === 'openai'
            ? V1ChatCompletionRequest::class
            : V0ChatCompletionRequest::class;

        // Initialize chat history
        $this->history = new ChatHistory;
        $this->history->addMessage(Message::system($systemMessage));

        $io->title('LMStudio Interactive Chat');

        // Display client info
        $this->displayClientInfo($io, $client, $model, $api);
        $io->section("System: {$systemMessage}");
        $io->newLine();

        // Start the chat loop
        $this->startChatLoop($io, $input, $output, $client, $model, $temperature, $stream);

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
        bool $stream
    ): void {
        $exitCommands = ['/exit', '/quit', '/q'];
        $clearCommand = '/clear';
        $helpCommand = '/help';

        $io->writeln('<info>Type your message and press Enter to chat with the model.</info>');
        $io->writeln('<info>Type /exit, /quit, or /q to end the chat.</info>');
        $io->writeln('<info>Type /clear to clear the chat history.</info>');
        $io->writeln('<info>Type /help to see available commands.</info>');
        $io->newLine();

        while (true) {
            $userInput = $io->ask('🧑‍💻 <fg=blue>You:</> ');

            // Check for exit command
            if (in_array($userInput, $exitCommands, true)) {
                $io->writeln('<info>Ending chat session. Goodbye!</info>');

                break;
            }

            // Check for clear command
            if ($userInput === $clearCommand) {
                $this->history = new ChatHistory;
                $this->history->addMessage(Message::system($input->getOption('system')));
                $io->writeln('<info>Chat history cleared. Only the system message remains.</info>');

                continue;
            }

            // Check for help command
            if ($userInput === $helpCommand) {
                $this->displayHelp($io);

                continue;
            }

            // Add user message to history
            $this->history->addMessage(Message::user($userInput));

            // Create request
            $requestClass = $this->requestClass;
            /** @var V0ChatCompletionRequest|V1ChatCompletionRequest $request */
            $request = new $requestClass($this->history, $model);

            // Apply temperature setting
            $request = $request->withTemperature($temperature);

            $io->write('🤖 <fg=green>Assistant:</> ');

            try {
                if ($stream) {
                    // Handle streaming response
                    $response = $client->streamChatCompletion($request);
                    $fullResponse = '';

                    foreach ($response as $chunk) {
                        // Check for error in the chunk
                        if (is_array($chunk) && isset($chunk['error'])) {
                            throw new \Exception($chunk['error']);
                        }

                        if (is_array($chunk) && isset($chunk['choices'][0]['delta']['content'])) {
                            $content = $chunk['choices'][0]['delta']['content'];
                            $fullResponse .= $content;
                            $io->write($content);
                        }
                    }

                    // Add assistant's response to history
                    $this->history->addMessage(Message::assistant($fullResponse));
                } else {
                    // Handle non-streaming response
                    $response = $client->chatCompletion($request);

                    // Check if the response contains an error
                    if (is_array($response) && isset($response['error'])) {
                        throw new \Exception($response['error']);
                    }

                    $content = '';

                    if (is_object($response) && isset($response->choices[0]->message->content)) {
                        $content = $response->choices[0]->message->content;
                    } elseif (is_array($response) && isset($response['choices'][0]['message']['content'])) {
                        $content = $response['choices'][0]['message']['content'];
                    }

                    $io->writeln($content);

                    // Add assistant's response to history
                    $this->history->addMessage(Message::assistant($content));
                }
            } catch (\Exception $e) {
                $io->error('Error: '.$e->getMessage());
            }

            $io->newLine();
        }
    }

    /**
     * Displays help information
     */
    private function displayHelp(SymfonyStyle $io): void
    {
        $io->section('Available Commands');
        $io->listing([
            '/exit, /quit, /q - End the chat session',
            '/clear - Clear the chat history (keeps the system message)',
            '/help - Display this help message',
        ]);
    }
}
