<?php

namespace Shelfwood\LMStudio\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Shelfwood\LMStudio\Facades\LMStudio;

class Models extends Command
{
    protected static $defaultName = 'models';
    protected static $defaultDescription = 'List all available models from LMStudio';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $models = LMStudio::listModels();

            $table = new Table($output);
            $table->setHeaders(['ID', 'Type', 'Publisher', 'State', 'Max Context']);

            foreach ($models['data'] as $model) {
                $table->addRow([
                    $model['id'],
                    $model['type'],
                    $model['publisher'],
                    $model['state'],
                    $model['max_context_length'],
                ]);
            }

            $table->render();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}