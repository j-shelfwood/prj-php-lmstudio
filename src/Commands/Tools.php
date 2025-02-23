<?php

namespace Shelfwood\LMStudio\Commands;

use Shelfwood\LMStudio\LMStudio;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'tools',
    description: 'Test tool calls with LMStudio models'
)]
class Tools extends Command
{
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
            ['role' => 'system', 'content' => 'You are a helpful assistant. Use the get_current_weather function to check weather conditions. Always use valid JSON for tool call arguments.'],
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
                    ->withToolHandler('get_current_weather', function ($args) use ($output, $model) {
                        if (! isset($args['location'])) {
                            throw new \InvalidArgumentException('Location is required for weather lookup');
                        }

                        $output->writeln("\n<comment>Fetching weather for: {$args['location']}</comment>");

                        // Mock weather response
                        $weather = [
                            'temperature' => rand(15, 25),
                            'condition' => ['sunny', 'cloudy', 'rainy'][rand(0, 2)],
                            'location' => $args['location'],
                        ];

                        $weatherJson = json_encode($weather);
                        $output->writeln("<comment>Weather data: {$weatherJson}</comment>\n");

                        // Suggest using the tool:response command
                        $output->writeln('<info>To get a response for this tool call, run:</info>');
                        $output->writeln("<comment>./bin/lmstudio tool:response --model {$model} get_current_weather '{$weatherJson}'</comment>\n");

                        return $weather;
                    })
                    ->stream()
                    ->send();

                $content = '';
                $toolCallContent = '';
                $inToolCall = false;

                foreach ($response as $chunk) {
                    if (is_string($chunk)) {
                        // Check if this is a tool call start
                        if (str_contains($chunk, '"tool_calls"')) {
                            $inToolCall = true;
                            $toolCallContent = '';

                            continue;
                        }

                        // If we're in a tool call, accumulate the JSON
                        if ($inToolCall) {
                            $toolCallContent .= $chunk;

                            try {
                                if ($json = json_decode($toolCallContent, true, 512, JSON_THROW_ON_ERROR)) {
                                    $output->writeln("\n<info>Tool Call:</info>");
                                    $output->writeln("<comment>$toolCallContent</comment>\n");
                                    $inToolCall = false;

                                    // After successfully decoding the accumulated JSON...
                                    if ($json = json_decode($toolCallContent, true, 512, JSON_THROW_ON_ERROR)) {
                                        $output->writeln("\n<info>Tool Call:</info>");
                                        $output->writeln("<comment>$toolCallContent</comment>\n");
                                        $inToolCall = false;

                                        // Extract the tool call from the nested structure
                                        $toolCall = $json['choices'][0]['delta']['tool_calls'][0] ?? null;
                                        if (! $toolCall || ! isset($toolCall['function']['arguments'])) {
                                            $output->writeln('<error>Invalid tool call: Missing arguments</error>');

                                            return Command::FAILURE;
                                        }

                                        $arguments = $toolCall['function']['arguments'];

                                        // Try to decode the arguments and verify that they decode to an associative array
                                        try {
                                            $decodedArgs = json_decode($arguments, true, 512, JSON_THROW_ON_ERROR);
                                            if (! is_array($decodedArgs)) {
                                                $output->writeln('<error>Invalid tool call: Tool call arguments must be a valid JSON object</error>');

                                                return Command::FAILURE;
                                            }
                                        } catch (\JsonException $e) {
                                            $output->writeln('<error>Invalid tool call: Tool call arguments must be a valid JSON object</error>');

                                            return Command::FAILURE;
                                        }

                                        continue;
                                    }
                                }
                            } catch (\JsonException $e) {
                                if (str_contains($toolCallContent, '"arguments"')) {
                                    $output->writeln('<error>Invalid tool call: Invalid JSON in arguments</error>');

                                    return Command::FAILURE;
                                }

                                continue;
                            }

                            continue;
                        }

                        // Regular content
                        $output->write($chunk);
                    }
                }

                $output->writeln("\n");
            } catch (\Exception $e) {
                $output->writeln('<error>Error: '.$e->getMessage().'</error>');

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
