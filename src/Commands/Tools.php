<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Commands;

use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Chat\Role;
use Shelfwood\LMStudio\DTOs\Tool\ToolCall;
use Shelfwood\LMStudio\DTOs\Tool\ToolFunction;
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
                $this->lmstudio->getConfig()->defaultModel
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
        $weatherTool = new ToolFunction(
            name: 'get_current_weather',
            description: 'Get the current weather in a location',
            parameters: [
                'location' => [
                    'type' => 'string',
                    'description' => 'The location to get weather for',
                ],
            ],
            required: ['location'],
        );

        $tools = [new ToolCall(uniqid('call_'), 'function', $weatherTool)];

        $output->writeln("<info>Testing tool calls with model: {$model}</info>");
        $output->writeln("<info>Type 'exit' to end the session</info>\n");

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $messages = [
            new Message(
                role: Role::SYSTEM,
                content: 'You are a helpful assistant. Use the get_current_weather function to check weather conditions. Always use valid JSON for tool call arguments.'
            ),
        ];

        while (true) {
            $question = new Question('<question>Ask about the weather:</question> ');
            $userInput = $helper->ask($input, $output, $question);

            if ($userInput === 'exit') {
                break;
            }

            try {
                $messages[] = new Message(Role::USER, $userInput);
                $output->write('<info>Assistant:</info> ');

                $response = $this->lmstudio->chat()
                    ->withModel($model)
                    ->withMessages($messages)
                    ->withTools([$weatherTool])
                    ->withToolHandler('get_current_weather', function (array $args) use ($output, $model) {
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

                foreach ($response as $chunk) {
                    if ($chunk->type === 'message' && $chunk->message !== null) {
                        if ($chunk->message->role === Role::TOOL) {
                            $output->writeln("\n<comment>Tool response: {$chunk->message->content}</comment>\n");
                        } else {
                            $output->write($chunk->message->content);
                        }
                    } elseif ($chunk->type === 'tool_call' && $chunk->toolCall !== null) {
                        if ($chunk->toolCall->function->name !== 'get_current_weather') {
                            $output->writeln("<e>No handler registered for tool: {$chunk->toolCall->function->name}</e>");

                            return Command::FAILURE;
                        }
                    }
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
