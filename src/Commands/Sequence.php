<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Commands;

use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Requests\V0\ChatCompletionRequest as V0ChatCompletionRequest;
use Shelfwood\LMStudio\Requests\V1\ChatCompletionRequest as V1ChatCompletionRequest;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;
use Shelfwood\LMStudio\ValueObjects\Tool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sequence',
    description: 'Run a sequence of API calls to test the LM Studio API'
)]
class Sequence extends BaseCommand
{
    /**
     * The results of the sequence.
     *
     * @var array<string, array{status: string, message: string}>
     */
    private array $results = [];

    /**
     * Configure the command.
     */
    protected function configure(): void
    {
        $this->configureCommonOptions();

        $this->addOption(
            'detailed',
            'd',
            InputOption::VALUE_NONE,
            'Show detailed output'
        );
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('LM Studio API Sequence Test');

        // Get the client based on the API option
        $client = $this->getClient($input, $io);

        if (! $client) {
            return Command::FAILURE;
        }

        // Get the model to use
        $model = $this->getModel($input, $client, $io);

        if (! $model) {
            return Command::FAILURE;
        }

        // Get the API type
        $api = $input->getOption('api');

        // Get the detailed flag
        $detailed = $input->getOption('detailed');

        // Initialize results array
        $this->results = [];

        // Display client information
        $this->displayClientInfo($io, $client, $model, $api);

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

        // Test 7: Tool Functions
        $this->testToolFunctions($io, $client, $model, $detailed);

        // Display summary table
        $this->displaySummary($io);

        return Command::SUCCESS;
    }

    /**
     * Tests the list models endpoint
     */
    private function testListModels(SymfonyStyle $io, LMStudioClientInterface $client, bool $detailed): void
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
    private function testChatCompletion(SymfonyStyle $io, LMStudioClientInterface $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Chat Completion (non-streaming)');

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Say hello and introduce yourself briefly.'],
            ];

            // Display the prompt
            $io->writeln('🧑‍💻 <fg=blue>User:</> Say hello and introduce yourself briefly.');

            $response = $client->chat($messages, [
                'model' => $model,
                'temperature' => 0.7,
                'max_tokens' => 150,
            ]);

            // Check if the response contains an error
            if (is_array($response) && isset($response['error'])) {
                throw new \Exception($response['error']);
            }

            // Always show the response content
            if (is_object($response) && isset($response->choices[0]->message->content)) {
                $content = $response->choices[0]->message->content;
                $io->writeln('🤖 <fg=green>Assistant:</> '.$content);
            } elseif (is_array($response) && isset($response['choices'][0]['message']['content'])) {
                $content = $response['choices'][0]['message']['content'];
                $io->writeln('🤖 <fg=green>Assistant:</> '.$content);
            }

            if ($detailed) {
                $io->writeln('<info>Detailed Response:</info>');

                if (is_object($response) && method_exists($response, 'jsonSerialize')) {
                    $responseData = $response->jsonSerialize();
                    $encoded = json_encode($responseData, JSON_PRETTY_PRINT);
                    $io->writeln($encoded !== false ? $encoded : 'Error encoding response');
                } else {
                    $encoded = json_encode($response, JSON_PRETTY_PRINT);
                    $io->writeln($encoded !== false ? $encoded : 'Error encoding response');
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
    private function testTextCompletion(SymfonyStyle $io, LMStudioClientInterface $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Text Completion (non-streaming)');

        try {
            $prompt = 'Write a short poem about artificial intelligence.';

            // Display the prompt
            $io->writeln('🧑‍💻 <fg=blue>Prompt:</> '.$prompt);

            $response = $client->completion($prompt, [
                'model' => $model,
                'temperature' => 0.7,
                'max_tokens' => 150,
            ]);

            // Check if the response contains an error
            if (is_array($response) && isset($response['error'])) {
                throw new \Exception($response['error']);
            }

            // Always show the response content
            if (is_object($response) && isset($response->choices[0]->text)) {
                $content = $response->choices[0]->text;
                $io->writeln('🤖 <fg=green>Response:</> '.$content);
            } elseif (is_array($response) && isset($response['choices'][0]['text'])) {
                $content = $response['choices'][0]['text'];
                $io->writeln('🤖 <fg=green>Response:</> '.$content);
            }

            if ($detailed) {
                $io->writeln('<info>Detailed Response:</info>');

                if (is_object($response) && method_exists($response, 'jsonSerialize')) {
                    $responseData = $response->jsonSerialize();
                    $encoded = json_encode($responseData, JSON_PRETTY_PRINT);
                    $io->writeln($encoded !== false ? $encoded : 'Error encoding response');
                } else {
                    $encoded = json_encode($response, JSON_PRETTY_PRINT);
                    $io->writeln($encoded !== false ? $encoded : 'Error encoding response');
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
    private function testEmbeddings(SymfonyStyle $io, LMStudioClientInterface $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Embeddings');

        try {
            $text = 'This is a test text for embeddings.';

            // Use a specific embedding model instead of the default model
            $embeddingModel = 'text-embedding-nomic-embed-text-v1.5';
            $io->note("Using embedding model: {$embeddingModel} instead of {$model}");

            $response = $client->embeddings($text, [
                'model' => $embeddingModel,
            ]);

            // Check if the response contains an error
            if (is_array($response) && isset($response['error'])) {
                throw new \Exception($response['error']);
            }

            if ($detailed) {
                $io->writeln('<info>Response:</info>');

                // Get embedding dimensions
                $dimensions = 0;

                if (is_object($response) && method_exists($response, 'jsonSerialize')) {
                    $responseData = $response->jsonSerialize();

                    if (isset($responseData['data'][0]['embedding'])) {
                        $dimensions = count($responseData['data'][0]['embedding']);
                    } elseif (isset($response->data) && is_array($response->data) && ! empty($response->data)) {
                        $dimensions = count($response->data[0]->embedding ?? []);
                    }
                } elseif (is_array($response) && isset($response['data'][0]['embedding'])) {
                    $dimensions = count($response['data'][0]['embedding']);
                }

                $io->writeln("Embedding dimensions: {$dimensions}");
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
    private function testChatCompletionStream(SymfonyStyle $io, LMStudioClientInterface $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Chat Completion (streaming)');

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Count from 1 to 5 and explain why counting is important.'],
            ];

            // Display the prompt
            $io->writeln('🧑‍💻 <fg=blue>User:</> Count from 1 to 5 and explain why counting is important.');
            $io->writeln('🤖 <fg=green>Assistant:</> ');

            $response = $client->streamChat($messages, [
                'model' => $model,
                'temperature' => 0.7,
                'max_tokens' => 150,
            ]);

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

                    // Always show the content as it streams in
                    $io->write($content);
                }
            }

            $io->newLine(2);

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
    private function testTextCompletionStream(SymfonyStyle $io, LMStudioClientInterface $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Text Completion (streaming)');

        try {
            $prompt = 'Write a short story about a robot that learns to love.';

            // Display the prompt
            $io->writeln('🧑‍💻 <fg=blue>Prompt:</> '.$prompt);
            $io->writeln('🤖 <fg=green>Response:</> ');

            $response = $client->streamCompletion($prompt, [
                'model' => $model,
                'temperature' => 0.7,
                'max_tokens' => 150,
            ]);

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

                    // Always show the content as it streams in
                    $io->write($content);
                }
            }

            $io->newLine(2);

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
     * Tests the tool functions feature
     */
    private function testToolFunctions(SymfonyStyle $io, LMStudioClientInterface $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Tool Functions');

        try {
            // Define a weather tool
            $weatherTool = Tool::function(
                'get_weather',
                'Get the current weather in a given location',
                [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The city and state, e.g. San Francisco, CA',
                        'required' => true,
                    ],
                    'unit' => [
                        'type' => 'string',
                        'enum' => ['celsius', 'fahrenheit'],
                        'description' => 'The unit of temperature',
                        'required' => false,
                    ],
                ]
            );

            // Create a chat history
            $history = new ChatHistory([
                Message::system('You are a helpful assistant that can provide weather information.'),
                Message::user('What\'s the weather like in San Francisco?'),
            ]);

            // Display the conversation
            $io->writeln('💻 <fg=yellow>System:</> You are a helpful assistant that can provide weather information.');
            $io->writeln('🧑‍💻 <fg=blue>User:</> What\'s the weather like in San Francisco?');

            // Create a request object based on the API type
            $requestClass = $client === $this->lmstudio->openai()
                ? V1ChatCompletionRequest::class
                : V0ChatCompletionRequest::class;

            $request = new $requestClass($history, $model);
            $request = $request
                ->withTools([$weatherTool])
                ->withToolChoice('auto');

            // Get a completion
            $response = $client->chatCompletion($request);

            // Check if the model used a tool
            $choice = $response->choices[0];
            $content = $choice->message->content ?? '';

            // Check for standard OpenAI tool calls
            $usedTool = isset($choice->message->toolCalls) && ! empty($choice->message->toolCalls);

            // Check for LM Studio's custom tool call format
            $lmStudioToolCall = null;

            if (! $usedTool && preg_match('/<TOOL_REQUEST>\s*({.*})\s*\[END_TOOL_REQUEST\]/s', $content, $matches)) {
                $usedTool = true;
                $toolCallData = json_decode($matches[1], true);

                // Create a simple object to mimic the OpenAI toolCalls structure
                $lmStudioToolCall = (object) [
                    'id' => 'call_'.uniqid(),
                    'function' => (object) [
                        'name' => $toolCallData['name'] ?? '',
                        'arguments' => json_encode($toolCallData['arguments'] ?? []),
                    ],
                ];
            }

            if ($detailed) {
                $io->writeln('<info>Detailed Response:</info>');

                if (is_object($response) && method_exists($response, 'jsonSerialize')) {
                    $responseData = $response->jsonSerialize();
                    $encoded = json_encode($responseData, JSON_PRETTY_PRINT);
                    $io->writeln($encoded !== false ? $encoded : 'Error encoding response');
                } else {
                    $encoded = json_encode($response, JSON_PRETTY_PRINT);
                    $io->writeln($encoded !== false ? $encoded : 'Error encoding response');
                }
            }

            if ($usedTool) {
                $toolCall = $lmStudioToolCall ?? $choice->message->toolCalls[0];
                $io->writeln('🔧 <fg=magenta>Tool Call:</> '.$toolCall->function->name);

                // Format the arguments as JSON for better readability
                $args = json_decode($toolCall->function->arguments, true);
                $formattedArgs = json_encode($args, JSON_PRETTY_PRINT);
                $io->writeln('📝 <fg=magenta>Arguments:</> '.$formattedArgs);

                // Simulate getting the weather
                $location = $args['location'] ?? 'unknown';
                $unit = $args['unit'] ?? 'celsius';
                $weatherResponse = "It's 72°F (22°C) and sunny in {$location}.";

                // Display the tool response
                $io->writeln('☀️ <fg=cyan>Weather API:</> '.$weatherResponse);

                // Add the tool response to the chat history
                $history->addMessage(Message::tool($weatherResponse, $toolCall->id));

                // Create a new request with the updated history
                $request = new $requestClass($history, $model);

                // Get a new completion that incorporates the tool response
                $response = $client->chatCompletion($request);

                if ($detailed) {
                    $io->writeln('<info>Detailed Final Response:</info>');

                    if (is_object($response) && method_exists($response, 'jsonSerialize')) {
                        $responseData = $response->jsonSerialize();
                        $encoded = json_encode($responseData, JSON_PRETTY_PRINT);
                        $io->writeln($encoded !== false ? $encoded : 'Error encoding response');
                    } else {
                        $encoded = json_encode($response, JSON_PRETTY_PRINT);
                        $io->writeln($encoded !== false ? $encoded : 'Error encoding response');
                    }
                }

                $io->writeln('🤖 <fg=green>Assistant:</> '.$response->choices[0]->message->content);
            } else {
                $io->writeln('🤖 <fg=green>Assistant (no tool used):</> '.$content);
            }

            $this->results['Tool Functions'] = [
                'status' => 'Success',
                'message' => $usedTool ? 'Successfully used tool function' : 'Completed without using tool',
            ];

            $io->success('Successfully tested tool functions');
        } catch (\Exception $e) {
            $this->results['Tool Functions'] = [
                'status' => 'Failed',
                'message' => $e->getMessage(),
            ];

            $io->error('Failed tool functions test: '.$e->getMessage());
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
