<?php

namespace Shelfwood\LMStudio\Commands;

use Shelfwood\LMStudio\LMStudio;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Tools extends Command
{
    protected static $defaultName = 'tools';

    protected static $defaultDescription = 'Test tool calls with LMStudio models';

    public function __construct(private LMStudio $lmstudio)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'model',
                'm',
                InputOption::VALUE_OPTIONAL,
                'The model to use',
                $this->lmstudio->getConfig()['default_model'] ?? null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $model = $input->getOption('model');

        if (! $model) {
            $output->writeln('<error>No model specified. Please provide a model with --model option.</error>');

            return Command::FAILURE;
        }

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
                                'description' => 'The location to get weather for',
                            ],
                        ],
                        'required' => ['location'],
                    ],
                ],
            ],
        ];

        $output->writeln("<info>Testing tool calls with model: {$model}</info>");
        $output->writeln("<info>Type 'exit' to end the session</info>\n");

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful weather assistant. Use the get_current_weather function to check weather conditions and provide helpful responses.'],
        ];

        while (true) {
            $question = new Question('<question>Ask about the weather:</question> ');
            $userInput = $helper->ask($input, $output, $question);

            if ($userInput === 'exit') {
                break;
            }

            try {
                $messages[] = ['role' => 'user', 'content' => $userInput];
                $output->write('<info>Assistant:</info> ');

                $response = $this->lmstudio->chat()
                    ->withModel($model)
                    ->withMessages($messages)
                    ->withTools($tools)
                    ->withToolHandler('get_current_weather', function ($args) use ($output) {
                        $output->writeln("\n<comment>Fetching weather for: {$args['location']}</comment>");

                        // Mock weather response
                        $weather = [
                            'temperature' => rand(15, 25),
                            'condition' => ['sunny', 'cloudy', 'rainy'][rand(0, 2)],
                            'location' => $args['location'],
                        ];

                        $output->writeln('<comment>Weather data: '.json_encode($weather)."</comment>\n");

                        return $weather;
                    })
                    ->stream(function ($chunk) use ($output) {
                        if ($chunk->isToolCall) {
                            $output->writeln("\n<comment>Making tool call: ".json_encode($chunk->toolCall).'</comment>');
                        } else {
                            $output->write($chunk->content);
                        }
                    });

                // Add the response to messages for context
                if (! empty($response)) {
                    $messages[] = ['role' => 'assistant', 'content' => $response];
                }

                $output->writeln("\n");
            } catch (\Exception $e) {
                $output->writeln("<error>Error: {$e->getMessage()}</error>");

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
