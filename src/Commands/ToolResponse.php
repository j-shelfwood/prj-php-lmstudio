<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Commands;

use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Chat\Role;
use Shelfwood\LMStudio\LMStudio;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'tool:response',
    description: 'Get a response from the model after a tool call'
)]
class ToolResponse extends Command
{
    public function __construct(private LMStudio $lmstudio)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'model',
                'm',
                InputOption::VALUE_OPTIONAL,
                'The model to use',
                $this->lmstudio->getConfig()->defaultModel
            )
            ->addArgument(
                'tool',
                InputArgument::REQUIRED,
                'The name of the tool that was called'
            )
            ->addArgument(
                'result',
                InputArgument::REQUIRED,
                'The JSON result from the tool call'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $model = $input->getOption('model');
        $tool = $input->getArgument('tool');
        $result = $input->getArgument('result');

        if (! $model) {
            $output->writeln('<error>No model specified. Please provide a model with --model option.</error>');

            return Command::FAILURE;
        }

        try {
            $decodedResult = json_decode($result, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $output->writeln('<error>Invalid JSON result provided.</error>');

                return Command::FAILURE;
            }

            $response = $this->lmstudio->chat()
                ->withModel($model)
                ->withMessages([
                    new Message(
                        role: Role::SYSTEM,
                        content: 'You are a helpful assistant. Provide a natural response based on the tool call result.'
                    ),
                    new Message(
                        role: Role::TOOL,
                        content: $result,
                        name: $tool
                    ),
                ])
                ->stream()
                ->send();

            $output->write('<info>Assistant:</info> ');

            foreach ($response as $chunk) {
                if ($chunk->type === 'message' && $chunk->message !== null) {
                    $output->write($chunk->message->content);
                }
            }
            $output->writeln("\n");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");

            return Command::FAILURE;
        }
    }
}
