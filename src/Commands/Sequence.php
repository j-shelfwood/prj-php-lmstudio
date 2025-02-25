<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Commands;

use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Chat\Role;
use Shelfwood\LMStudio\DTOs\Tool\ToolFunction;
use Shelfwood\LMStudio\LMStudio;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sequence',
    description: 'Run a sequence of API calls to test all LMStudio endpoints'
)]
class Sequence extends Command
{
    private array $results = [];

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
                'The model to use for testing',
                $this->lmstudio->getConfig()->defaultModel
            )
            ->addOption(
                'detailed',
                'd',
                InputOption::VALUE_NONE,
                'Show detailed output for each test'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $model = $input->getOption('model');
        $detailed = $input->getOption('detailed');

        if (! $model) {
            $io->error('No model specified. Please provide a model with --model option.');

            return Command::FAILURE;
        }

        $io->title('LMStudio API Sequence Test');
        $io->section("Using model: {$model}");

        // Test 1: List Models
        $this->testListModels($io, $detailed);

        // Test 2: Get Model Info
        $this->testGetModel($io, $model, $detailed);

        // Test 3: Chat Completion (non-streaming)
        $this->testChatCompletion($io, $model, $detailed);

        // Test 4: Chat Completion (streaming)
        $this->testChatCompletionStream($io, $model, $detailed);

        // Test 5: Tool Calls
        $this->testToolCalls($io, $model, $detailed);

        // Display summary table
        $this->displaySummary($io);

        return Command::SUCCESS;
    }

    private function testListModels(SymfonyStyle $io, bool $detailed): void
    {
        $io->section('Testing: List Models');

        try {
            $modelList = $this->lmstudio->listModels();

            if ($detailed) {
                $table = new Table($io);
                $table->setHeaders(['ID', 'Object', 'Owner']);

                foreach ($modelList->data as $model) {
                    $table->addRow([
                        $model->id,
                        $model->object,
                        $model->ownedBy,
                    ]);
                }

                $table->render();
            }

            $this->results['List Models'] = [
                'status' => 'Success',
                'message' => sprintf('Found %d models', count($modelList->data)),
            ];

            $io->success('Successfully retrieved model list');
        } catch (\Exception $e) {
            $this->results['List Models'] = [
                'status' => 'Failed',
                'message' => $e->getMessage(),
            ];

            $io->error('Failed to list models: '.$e->getMessage());
        }
    }

    private function testGetModel(SymfonyStyle $io, string $model, bool $detailed): void
    {
        $io->section('Testing: Get Model Info');

        try {
            $modelInfo = $this->lmstudio->getModel($model);

            if ($detailed) {
                $definitionList = [
                    ['ID' => $modelInfo->id],
                    ['Object' => $modelInfo->object],
                    ['Owner' => $modelInfo->ownedBy],
                ];

                if ($modelInfo->created !== null) {
                    $definitionList[] = ['Created' => date('Y-m-d H:i:s', $modelInfo->created)];
                }

                $io->definitionList(...$definitionList);
            }

            $this->results['Get Model Info'] = [
                'status' => 'Success',
                'message' => "Retrieved info for model: {$modelInfo->id}",
            ];

            $io->success('Successfully retrieved model info');
        } catch (\Exception $e) {
            $this->results['Get Model Info'] = [
                'status' => 'Failed',
                'message' => $e->getMessage(),
            ];

            $io->error('Failed to get model info: '.$e->getMessage());
        }
    }

    private function testChatCompletion(SymfonyStyle $io, string $model, bool $detailed): void
    {
        $io->section('Testing: Chat Completion (non-streaming)');

        try {
            $messages = [
                new Message(Role::SYSTEM, 'You are a helpful assistant.'),
                new Message(Role::USER, 'Say hello and introduce yourself briefly.'),
            ];

            $response = $this->lmstudio->createChatCompletion($messages, $model);

            if ($detailed) {
                $io->writeln('<info>Response:</info>');
                $io->writeln($response->choices[0]->message->content);
            }

            $this->results['Chat Completion'] = [
                'status' => 'Success',
                'message' => 'Received response with '.strlen($response->choices[0]->message->content).' characters',
            ];

            $io->success('Successfully completed chat completion');
        } catch (\Exception $e) {
            $this->results['Chat Completion'] = [
                'status' => 'Failed',
                'message' => $e->getMessage(),
            ];

            $io->error('Failed chat completion: '.$e->getMessage());
        }
    }

    private function testChatCompletionStream(SymfonyStyle $io, string $model, bool $detailed): void
    {
        $io->section('Testing: Chat Completion (streaming)');

        try {
            $messages = [
                new Message(Role::SYSTEM, 'You are a helpful assistant.'),
                new Message(Role::USER, 'Count from 1 to 5 and explain why counting is important.'),
            ];

            $response = $this->lmstudio->createChatCompletionStream($messages, $model);

            if ($detailed) {
                $io->writeln('<info>Streaming Response:</info>');
                $fullResponse = '';

                foreach ($response as $chunk) {
                    if ($chunk->type === 'message' && $chunk->message !== null) {
                        $content = $chunk->message->content ?? '';
                        $fullResponse .= $content;
                        $io->write($content);
                    }
                }

                $io->newLine(2);
            } else {
                $fullResponse = '';

                foreach ($response as $chunk) {
                    if ($chunk->type === 'message' && $chunk->message !== null) {
                        $fullResponse .= $chunk->message->content ?? '';
                    }
                }
            }

            $this->results['Chat Completion Stream'] = [
                'status' => 'Success',
                'message' => 'Received streaming response with '.strlen($fullResponse).' characters',
            ];

            $io->success('Successfully completed streaming chat completion');
        } catch (\Exception $e) {
            $this->results['Chat Completion Stream'] = [
                'status' => 'Failed',
                'message' => $e->getMessage(),
            ];

            $io->error('Failed streaming chat completion: '.$e->getMessage());
        }
    }

    private function testToolCalls(SymfonyStyle $io, string $model, bool $detailed): void
    {
        $io->section('Testing: Tool Calls');

        try {
            // Define a weather tool
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

            $messages = [
                new Message(Role::SYSTEM, 'You are a helpful assistant. Use the get_current_weather function to check weather conditions.'),
                new Message(Role::USER, 'What\'s the weather like in New York?'),
            ];

            $response = $this->lmstudio->chat()
                ->withModel($model)
                ->withMessages($messages)
                ->withTools([$weatherTool])
                ->withToolHandler('get_current_weather', function (array $args) use ($io, $detailed): array {
                    if (! isset($args['location'])) {
                        throw new \InvalidArgumentException('Location is required for weather lookup');
                    }

                    if ($detailed) {
                        $io->writeln("<comment>Tool called with location: {$args['location']}</comment>");
                    }

                    // Mock weather response
                    return [
                        'temperature' => rand(15, 25),
                        'condition' => ['sunny', 'cloudy', 'rainy'][rand(0, 2)],
                        'location' => $args['location'],
                    ];
                })
                ->send();

            if ($detailed) {
                $io->writeln('<info>Response:</info>');

                if (is_object($response) && method_exists($response, 'jsonSerialize')) {
                    $responseData = $response->jsonSerialize();

                    if (isset($responseData['choices'][0]['message']['content'])) {
                        $io->writeln($responseData['choices'][0]['message']['content']);
                    } else {
                        $io->writeln(json_encode($responseData, JSON_PRETTY_PRINT));
                    }
                } else {
                    $io->writeln('Response is not in the expected format');
                }
            }

            $this->results['Tool Calls'] = [
                'status' => 'Success',
                'message' => 'Successfully executed tool call and received response',
            ];

            $io->success('Successfully tested tool calls');
        } catch (\Exception $e) {
            $this->results['Tool Calls'] = [
                'status' => 'Failed',
                'message' => $e->getMessage(),
            ];

            $io->error('Failed tool calls test: '.$e->getMessage());
        }
    }

    private function displaySummary(SymfonyStyle $io): void
    {
        $io->section('Test Summary');

        $table = new Table($io);
        $table->setHeaders(['Endpoint', 'Status', 'Message']);

        $successCount = 0;
        $failCount = 0;

        foreach ($this->results as $endpoint => $result) {
            $status = $result['status'];
            $statusFormatted = $status === 'Success'
                ? '<fg=green>Success</>'
                : '<fg=red>Failed</>';

            $table->addRow([$endpoint, $statusFormatted, $result['message']]);

            if ($status === 'Success') {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        $table->render();

        $io->newLine();
        $io->writeln(sprintf(
            '<info>Summary: %d/%d tests passed (%d failed)</info>',
            $successCount,
            count($this->results),
            $failCount
        ));

        if ($failCount === 0) {
            $io->success('All tests passed successfully!');
        } else {
            $io->warning('Some tests failed. Check the summary for details.');
        }
    }
}
