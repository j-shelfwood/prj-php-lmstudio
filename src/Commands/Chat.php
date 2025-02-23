<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Commands;

use Shelfwood\LMStudio\LMStudio;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Chat extends Command
{
    protected static $defaultName = 'chat';

    protected static $defaultDescription = 'Start an interactive chat session with LMStudio';

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
                'The model to use for chat',
                $this->lmstudio->getConfig()['default_model'] ?? null
            )
            ->addOption(
                'system',
                's',
                InputOption::VALUE_OPTIONAL,
                'System message to set the behavior',
                'You are a helpful assistant.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $model = $input->getOption('model');
        $systemMessage = $input->getOption('system');

        if (! $model) {
            $output->writeln('<error>No model specified. Please provide a model with --model option.</error>');

            return Command::FAILURE;
        }

        $output->writeln("<info>Starting chat with model: {$model}</info>");
        $output->writeln("<info>Type 'exit' to end the conversation</info>\n");

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $messages = [
            ['role' => 'system', 'content' => $systemMessage],
        ];

        while (true) {
            $question = new Question('<question>You:</question> ');
            $userInput = $helper->ask($input, $output, $question);

            if ($userInput === 'exit') {
                break;
            }

            $messages[] = ['role' => 'user', 'content' => $userInput];

            try {
                $output->write('<info>Assistant:</info> ');

                $response = $this->lmstudio->chat()
                    ->withModel($model)
                    ->withMessages($messages)
                    ->stream(function ($chunk) use ($output): void {
                        $output->write($chunk->content);
                    });

                $messages[] = ['role' => 'assistant', 'content' => $response];
                $output->writeln("\n");
            } catch (\Exception $e) {
                $output->writeln("<error>Error: {$e->getMessage()}</error>");

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
