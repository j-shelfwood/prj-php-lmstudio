<?php

namespace Shelfwood\LMStudio\Commands;

use Shelfwood\LMStudio\LMStudio;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Models extends Command
{
    protected static $defaultName = 'models';

    protected static $defaultDescription = 'List all available models from LMStudio';

    public function __construct(private LMStudio $lmstudio)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $models = $this->lmstudio->listModels();

            $table = new Table($output);
            $table->setHeaders(['ID', 'Type', 'Publisher', 'State', 'Max Context']);

            foreach ($models['data'] as $model) {
                $table->addRow([
                    $model['id'],
                    $model['type'] ?? 'N/A',
                    $model['publisher'] ?? 'N/A',
                    $model['state'] ?? 'N/A',
                    $model['max_context_length'] ?? 'N/A',
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
