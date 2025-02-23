<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Commands;

use Shelfwood\LMStudio\DTOs\Model\ModelInfo;
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
            $modelList = $this->lmstudio->listModels();

            $table = new Table($output);
            $table->setHeaders(['ID', 'Object', 'Created', 'Owner', 'Root', 'Parent']);

            /** @var ModelInfo $model */
            foreach ($modelList->data as $model) {
                $table->addRow([
                    $model->id,
                    $model->object,
                    date('Y-m-d H:i:s', $model->created),
                    $model->ownedBy,
                    $model->root ?? 'N/A',
                    $model->parent ?? 'N/A',
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
