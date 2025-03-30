<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel\Commands;

use Illuminate\Console\Command;
use Shelfwood\LMStudio\LMStudioFactory;

class ModelsCommand extends Command
{
    protected $signature = 'lmstudio:models';

    protected $description = 'List available LM Studio models';

    public function handle(LMStudioFactory $factory): int
    {
        $modelService = $factory->createModelService();

        try {
            $modelResponse = $modelService->listModels();
            $models = $modelResponse->getModels();

            $this->info('Available models:');

            foreach ($models as $model) {
                $status = $model->isLoaded() ? '<fg=green>loaded</>' : '<fg=yellow>not loaded</>';
                $this->line(" - <fg=white>{$model->id}</> ({$status})");

                if ($model->isLoaded()) {
                    $this->line("   Type: {$model->type->value}");
                    $this->line("   Context length: {$model->maxContextLength}");
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to list models: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
