<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class BaseCommand extends Command
{
    protected InputInterface $input;

    protected OutputInterface $output;

    protected SymfonyStyle $io;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);

        return $this->handle();
    }

    abstract protected function handle(): int;

    protected function info(string $message): void
    {
        $this->io->writeln("<info>$message</info>");
    }

    protected function error(string $message): void
    {
        $this->io->error($message);
    }

    protected function line(string $message): void
    {
        $this->io->writeln($message);
    }

    protected function newLine(int $count = 1): void
    {
        $this->io->newLine($count);
    }

    protected function ask(string $question): string
    {
        return $this->io->ask($question);
    }

    protected function option(string $name): string
    {
        return $this->input->getOption($name);
    }

    /**
     * @param  callable(): (string|null)  $callback  Callback should return a string result or null
     */
    protected function runStep(int $number, string $description, callable $callback): void
    {
        $this->newLine();
        $this->info("Step $number: $description");
        $this->newLine();

        try {
            $result = $callback();
            $this->newLine();
            $this->info("✓ $description");

            if ($result) {
                $this->line("  ↪ $result");
            }
        } catch (\Exception $e) {
            $this->newLine();
            $this->error("✗ $description");
            $this->error("  ↪ Error: {$e->getMessage()}");
        }
    }
}
