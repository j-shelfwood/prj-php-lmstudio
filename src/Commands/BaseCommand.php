<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Commands;

use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\LMStudio;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class BaseCommand extends Command
{
    /**
     * The LMStudio instance.
     */
    protected LMStudio $lmstudio;

    /**
     * Create a new command instance.
     */
    public function __construct(LMStudio $lmstudio)
    {
        parent::__construct();
        $this->lmstudio = $lmstudio;
    }

    /**
     * Configure common options for LMStudio commands.
     */
    protected function configureCommonOptions(): void
    {
        $this
            ->addOption(
                'model',
                'm',
                InputOption::VALUE_OPTIONAL,
                'The model to use (uses default from config if not specified)'
            )
            ->addOption(
                'api',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Which API to use (openai or lms)',
                'lms'
            );
    }

    /**
     * Get the appropriate client based on the API option.
     */
    protected function getClient(InputInterface $input, SymfonyStyle $io): ?LMStudioClientInterface
    {
        $api = $input->getOption('api');

        if (! in_array($api, ['openai', 'lms'], true)) {
            $io->error('Invalid API specified. Please use "openai" or "lms".');

            return null;
        }

        return $api === 'openai' ? $this->lmstudio->openai() : $this->lmstudio->lms();
    }

    /**
     * Get the model to use, falling back to the default model from config if not specified.
     */
    protected function getModel(InputInterface $input, LMStudioClientInterface $client, SymfonyStyle $io): ?string
    {
        $model = $input->getOption('model');

        if (! $model) {
            // Get the default model from config
            $defaultModel = $client->getConfig()->getDefaultModel();

            if (! $defaultModel) {
                $io->error('No model specified and no default model found in config. Please provide a model with --model option.');

                return null;
            }

            $model = $defaultModel;
            $io->note("Using default model from config: {$model}");
        }

        return $model;
    }

    /**
     * Display common information about the client and model.
     */
    protected function displayClientInfo(
        SymfonyStyle $io,
        LMStudioClientInterface $client,
        string $model,
        string $api
    ): void {
        $config = $client->getConfig();

        $io->section("Using model: {$model}");
        $io->section("Using API: {$api}");
        $io->section('API URL: '.$config->getBaseUrl());
    }
}
