<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Console\Command;

use Symfony\Component\Console\Input\InputOption;

class ChatCommand extends BaseCommand
{
    public function __construct()
    {
        parent::__construct('chat');

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
                'System message to use'
            );
    }

    protected function handle(): int
    {
        // @TODO: Implement chat command

        return self::SUCCESS;
    }
}
