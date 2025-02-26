<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Commands;

use Shelfwood\LMStudio\DTOs\LMStudio\Model\ModelInfo;
use Shelfwood\LMStudio\Endpoints\LMStudio;
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
            $table->setHeaders(['ID', 'Object', 'Type', 'Publisher', 'Arch', 'State']);

            /** @var ModelInfo $model */
            foreach ($modelList->data as $model) {
                $table->addRow([
                    $model->id,
                    $model->object,
                    $model->type,
                    $model->publisher ?? 'N/A',
                    $model->arch ?? 'N/A',
                    $model->state,
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
