<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Commands;

use Shelfwood\LMStudio\DTOs\Common\Chat\Role;
use Shelfwood\LMStudio\Endpoints\LMStudio;
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
            ->setName('chat')
            ->setDescription('Start an interactive chat session')
            ->addOption(
                'model',
                'm',
                InputOption::VALUE_REQUIRED,
                'The model to use for chat',
                $this->lmstudio->getConfig()->defaultModel
            )
            ->addOption(
                'system',
                's',
                InputOption::VALUE_REQUIRED,
                'The system message to use',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $model = $input->getOption('model');
        $systemMessage = $input->getOption('system');

        if (empty($model)) {
            $output->writeln('<error>No model specified. Please provide a model using the --model option.</error>');

            return Command::FAILURE;
        }

        $chat = $this->lmstudio->chat()
            ->withModel($model);

        if ($systemMessage) {
            $chat->addMessage(Role::SYSTEM, $systemMessage);
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new Question('You: ');

        while (true) {
            $userInput = $helper->ask($input, $output, $question);

            if ($userInput === null || strtolower($userInput) === 'exit') {
                break;
            }

            $chat->addMessage(Role::USER, $userInput);
            $response = $chat->send();

            $output->writeln("\nAssistant: ".$response);
            $output->writeln('');
        }

        return Command::SUCCESS;
    }
}
