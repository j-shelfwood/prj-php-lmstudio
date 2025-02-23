<?php

namespace Shelfwood\LMStudio\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Shelfwood\LMStudio\Facades\LMStudio;

class Tools extends Command
{
    protected static $defaultName = 'tools';
    protected static $defaultDescription = 'Test tool calls with LMStudio models';

    protected function configure(): void
    {
        $this
            ->addOption(
                'model',
                'm',
                InputOption::VALUE_OPTIONAL,
                'The model to use',
                config('lmstudio.default_model')
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $model = $input->getOption('model');

        // Define example tools
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_weather',
                    'description' => 'Get the current weather in a location',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'location' => [
                                'type' => 'string',
                                'description' => 'The location to get weather for'
                            ]
                        ],
                        'required' => ['location']
                    ]
                ]
            ]
        ];

        $output->writeln("<info>Testing tool calls with model: {$model}</info>");
        $output->writeln("<info>Type 'exit' to end the session</info>\n");

        $helper = $this->getHelper('question');

        while (true) {
            $question = new Question('<question>Ask about the weather:</question> ');
            $userInput = $helper->ask($input, $output, $question);

            if ($userInput === 'exit') {
                break;
            }

            try {
                $response = LMStudio::chat()
                    ->withModel($model)
                    ->withMessages([
                        ['role' => 'user', 'content' => $userInput]
                    ])
                    ->withTools($tools)
                    ->withToolHandler('get_current_weather', function($args) {
                        // Mock weather response
                        return [
                            'temperature' => rand(15, 25),
                            'condition' => ['sunny', 'cloudy', 'rainy'][rand(0, 2)],
                            'location' => $args['location']
                        ];
                    })
                    ->stream(function($chunk) use ($output) {
                        if ($chunk->isToolCall) {
                            $output->writeln("<comment>Tool Call:</comment> " . json_encode($chunk->toolCall));
                        } else {
                            $output->write($chunk->content);
                        }
                    });

                $output->writeln("\n");
            } catch (\Exception $e) {
                $output->writeln("<error>Error: {$e->getMessage()}</error>");
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}