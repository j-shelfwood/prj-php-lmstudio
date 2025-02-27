<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Commands;

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
    /** @var array<string, array{status: string, message: string}> */
    private array $results = [];

    public function __construct(private LMStudio $lmstudio)
    {
        parent::__construct();
    }

    /**
     * Configures the command
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'model',
                'm',
                InputOption::VALUE_OPTIONAL,
                'The model to use for testing',
                'gpt-3.5-turbo'
            )
            ->addOption(
                'detailed',
                'd',
                InputOption::VALUE_NONE,
                'Show detailed output for each test'
            )
            ->addOption(
                'api',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Which API to use (openai or lms)',
                'openai'
            );
    }

    /**
     * Executes the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $model = $input->getOption('model');
        $detailed = $input->getOption('detailed');
        $api = $input->getOption('api');

        if (! $model) {
            $io->error('No model specified. Please provide a model with --model option.');

            return Command::FAILURE;
        }

        if (! in_array($api, ['openai', 'lms'], true)) {
            $io->error('Invalid API specified. Please use "openai" or "lms".');

            return Command::FAILURE;
        }

        $client = $api === 'openai' ? $this->lmstudio->openai() : $this->lmstudio->lms();

        // Get the configuration from the client using reflection
        $reflectionClass = new \ReflectionClass($client);
        $clientProperty = $reflectionClass->getProperty('client');
        $clientProperty->setAccessible(true);
        $httpClient = $clientProperty->getValue($client);

        $reflectionHttpClient = new \ReflectionClass($httpClient);
        $configProperty = $reflectionHttpClient->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($httpClient);

        $io->title('LMStudio API Sequence Test');
        $io->section("Using model: {$model}");
        $io->section("Using API: {$api}");
        $io->section('API URL: '.$config->getBaseUrl());

        // Test 1: List Models
        $this->testListModels($io, $client, $detailed);

        // Test 2: Chat Completion (non-streaming)
        $this->testChatCompletion($io, $client, $model, $detailed);

        // Test 3: Text Completion (non-streaming)
        $this->testTextCompletion($io, $client, $model, $detailed);

        // Test 4: Embeddings
        $this->testEmbeddings($io, $client, $model, $detailed);

        // Test 5: Chat Completion (streaming)
        $this->testChatCompletionStream($io, $client, $model, $detailed);

        // Test 6: Text Completion (streaming)
        $this->testTextCompletionStream($io, $client, $model, $detailed);

        // Display summary table
        $this->displaySummary($io);

        return Command::SUCCESS;
    }

    /**
     * Tests the list models endpoint
     */
    private function testListModels(SymfonyStyle $io, $client, bool $detailed): void
    {
        $io->section('Testing: List Models');

        try {
            $modelList = $client->models();

            // Check if the response contains an error
            if (isset($modelList['error'])) {
                throw new \Exception($modelList['error']);
            }

            if ($detailed) {
                $table = new Table($io);
                $table->setHeaders(['ID']);

                if (isset($modelList['data'])) {
                    foreach ($modelList['data'] as $model) {
                        $table->addRow([$model['id'] ?? 'Unknown']);
                    }
                } elseif (isset($modelList['models'])) {
                    foreach ($modelList['models'] as $model) {
                        $table->addRow([$model]);
                    }
                }

                $table->render();
            }

            $count = isset($modelList['data']) ? count($modelList['data']) :
                   (isset($modelList['models']) ? count($modelList['models']) : 0);

            $this->results['List Models'] = [
                'status' => 'Success',
                'message' => sprintf('Found %d models', $count),
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

    /**
     * Tests the chat completion endpoint (non-streaming)
     */
    private function testChatCompletion(SymfonyStyle $io, $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Chat Completion (non-streaming)');

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Say hello and introduce yourself briefly.'],
            ];

            $response = $client->chat($messages, [
                'model' => $model,
                'temperature' => 0.7,
                'max_tokens' => 150,
            ]);

            // Check if the response contains an error
            if (is_array($response) && isset($response['error'])) {
                throw new \Exception($response['error']);
            }

            if ($detailed) {
                $io->writeln('<info>Response:</info>');

                if (is_object($response) && method_exists($response, 'jsonSerialize')) {
                    $responseData = $response->jsonSerialize();
                    $io->writeln(json_encode($responseData, JSON_PRETTY_PRINT));
                } else {
                    $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
                }
            }

            $this->results['Chat Completion'] = [
                'status' => 'Success',
                'message' => 'Successfully received chat completion response',
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

    /**
     * Tests the text completion endpoint (non-streaming)
     */
    private function testTextCompletion(SymfonyStyle $io, $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Text Completion (non-streaming)');

        try {
            $prompt = 'Write a short poem about artificial intelligence.';

            $response = $client->completion($prompt, [
                'model' => $model,
                'temperature' => 0.7,
                'max_tokens' => 150,
            ]);

            // Check if the response contains an error
            if (is_array($response) && isset($response['error'])) {
                throw new \Exception($response['error']);
            }

            if ($detailed) {
                $io->writeln('<info>Response:</info>');

                if (is_object($response) && method_exists($response, 'jsonSerialize')) {
                    $responseData = $response->jsonSerialize();
                    $io->writeln(json_encode($responseData, JSON_PRETTY_PRINT));
                } else {
                    $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
                }
            }

            $this->results['Text Completion'] = [
                'status' => 'Success',
                'message' => 'Successfully received text completion response',
            ];

            $io->success('Successfully completed text completion');
        } catch (\Exception $e) {
            $this->results['Text Completion'] = [
                'status' => 'Failed',
                'message' => $e->getMessage(),
            ];

            $io->error('Failed text completion: '.$e->getMessage());
        }
    }

    /**
     * Tests the embeddings endpoint
     */
    private function testEmbeddings(SymfonyStyle $io, $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Embeddings');

        try {
            $text = 'This is a test text for embeddings.';

            $response = $client->embeddings($text, [
                'model' => $model,
            ]);

            // Check if the response contains an error
            if (is_array($response) && isset($response['error'])) {
                throw new \Exception($response['error']);
            }

            if ($detailed) {
                $io->writeln('<info>Response:</info>');

                if (is_object($response) && method_exists($response, 'jsonSerialize')) {
                    $responseData = $response->jsonSerialize();
                    $io->writeln('Embedding dimensions: '.count($responseData['data'][0]['embedding'] ?? []));
                } else {
                    $io->writeln('Embedding dimensions: '.count($response['data'][0]['embedding'] ?? []));
                }
            }

            $this->results['Embeddings'] = [
                'status' => 'Success',
                'message' => 'Successfully received embeddings response',
            ];

            $io->success('Successfully completed embeddings request');
        } catch (\Exception $e) {
            $this->results['Embeddings'] = [
                'status' => 'Failed',
                'message' => $e->getMessage(),
            ];

            $io->error('Failed embeddings request: '.$e->getMessage());
        }
    }

    /**
     * Tests the chat completion endpoint (streaming)
     */
    private function testChatCompletionStream(SymfonyStyle $io, $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Chat Completion (streaming)');

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Count from 1 to 5 and explain why counting is important.'],
            ];

            $response = $client->streamChat($messages, [
                'model' => $model,
                'temperature' => 0.7,
                'max_tokens' => 150,
            ]);

            if ($detailed) {
                $io->writeln('<info>Streaming Response:</info>');
                $fullResponse = '';
                $errorDetected = false;

                foreach ($response as $chunk) {
                    // Check for error in the chunk
                    if (is_array($chunk) && isset($chunk['error'])) {
                        throw new \Exception($chunk['error']);
                    }

                    if (is_array($chunk) && isset($chunk['choices'][0]['delta']['content'])) {
                        $content = $chunk['choices'][0]['delta']['content'];
                        $fullResponse .= $content;
                        $io->write($content);
                    }
                }

                $io->newLine(2);
            } else {
                $fullResponse = '';
                $errorDetected = false;

                foreach ($response as $chunk) {
                    // Check for error in the chunk
                    if (is_array($chunk) && isset($chunk['error'])) {
                        throw new \Exception($chunk['error']);
                    }

                    if (is_array($chunk) && isset($chunk['choices'][0]['delta']['content'])) {
                        $fullResponse .= $chunk['choices'][0]['delta']['content'];
                    }
                }
            }

            if (empty($fullResponse)) {
                throw new \Exception('No content received from streaming response');
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

    /**
     * Tests the text completion endpoint (streaming)
     */
    private function testTextCompletionStream(SymfonyStyle $io, $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Text Completion (streaming)');

        try {
            $prompt = 'Write a short story about a robot that learns to love.';

            $response = $client->streamCompletion($prompt, [
                'model' => $model,
                'temperature' => 0.7,
                'max_tokens' => 150,
            ]);

            if ($detailed) {
                $io->writeln('<info>Streaming Response:</info>');
                $fullResponse = '';
                $errorDetected = false;

                foreach ($response as $chunk) {
                    // Check for error in the chunk
                    if (is_array($chunk) && isset($chunk['error'])) {
                        throw new \Exception($chunk['error']);
                    }

                    if (is_array($chunk) && isset($chunk['choices'][0]['text'])) {
                        $content = $chunk['choices'][0]['text'];
                        $fullResponse .= $content;
                        $io->write($content);
                    }
                }

                $io->newLine(2);
            } else {
                $fullResponse = '';
                $errorDetected = false;

                foreach ($response as $chunk) {
                    // Check for error in the chunk
                    if (is_array($chunk) && isset($chunk['error'])) {
                        throw new \Exception($chunk['error']);
                    }

                    if (is_array($chunk) && isset($chunk['choices'][0]['text'])) {
                        $fullResponse .= $chunk['choices'][0]['text'];
                    }
                }
            }

            if (empty($fullResponse)) {
                throw new \Exception('No content received from streaming response');
            }

            $this->results['Text Completion Stream'] = [
                'status' => 'Success',
                'message' => 'Received streaming response with '.strlen($fullResponse).' characters',
            ];

            $io->success('Successfully completed streaming text completion');
        } catch (\Exception $e) {
            $this->results['Text Completion Stream'] = [
                'status' => 'Failed',
                'message' => $e->getMessage(),
            ];

            $io->error('Failed streaming text completion: '.$e->getMessage());
        }
    }

    /**
     * Displays the summary of the tests
     */
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
