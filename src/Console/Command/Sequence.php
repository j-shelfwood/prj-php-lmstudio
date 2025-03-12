<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Console\Command;

use Shelfwood\LMStudio\Api\Contract\LMStudioClientInterface;
use Shelfwood\LMStudio\Http\Request\V0\ChatCompletionRequest as V0ChatCompletionRequest;
use Shelfwood\LMStudio\Http\Request\V1\ChatCompletionRequest as V1ChatCompletionRequest;
use Shelfwood\LMStudio\ValueObject\ChatHistory;
use Shelfwood\LMStudio\ValueObject\Message;
use Shelfwood\LMStudio\ValueObject\Tool;
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

        // Test 8: Structured Output
        $this->testStructuredOutput($io, $client, $model, $detailed);

        // Test 9: Model Options
        $this->testModelOptions($io, $client, $model, $detailed);

        // Test 10: Model Information
        $this->testModelInfo($io, $client, $model, $detailed);

        // Test 11: Conversation Class
        $this->testConversationClass($io, $client, $model, $detailed);

        // Test 12: StreamBuilder
        $this->testStreamBuilder($io, $client, $model, $detailed);

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

            // Create a request object
            $apiVersion = $client->getApiVersionNamespace();
            $requestClass = "\\Shelfwood\\LMStudio\\Http\\Requests\\{$apiVersion}\\ChatCompletionRequest";
            $request = new $requestClass;
            $request->setMessages($messages);
            $request->setModel($model);
            $request->setTemperature(0.7);
            $request->setMaxTokens(150);

            $response = $client->chatCompletion($request);

            // Check if the response contains an error
            if (is_array($response) && isset($response['error'])) {
                throw new \Exception($response['error']);
            }

            // Extract the response content
            $content = '';

            if (is_array($response)) {
                $content = $response['choices'][0]['message']['content'] ?? '';
            } else {
                $content = $response->choices[0]->message->content ?? '';
            }

            // Display the response
            $io->writeln('🤖 <fg=green>Assistant:</> '.$content);

            // Display detailed information if requested
            if ($detailed) {
                $io->writeln('');
                $io->writeln('<fg=yellow>Response Details:</>');
                $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
            }

            $this->results['chat_completion'] = true;
        } catch (\Exception $e) {
            $this->results['chat_completion'] = false;
            $io->error('Error: '.$e->getMessage());
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

            // Create a request object
            $apiVersion = $client->getApiVersionNamespace();
            $requestClass = "\\Shelfwood\\LMStudio\\Http\\Requests\\{$apiVersion}\\TextCompletionRequest";
            $request = new $requestClass;
            $request->setPrompt($prompt);
            $request->setModel($model);
            $request->setTemperature(0.7);
            $request->setMaxTokens(150);

            $response = $client->textCompletion($request);

            // Check if the response contains an error
            if (is_array($response) && isset($response['error'])) {
                throw new \Exception($response['error']);
            }

            // Extract the response content
            $content = '';

            if (is_array($response)) {
                $content = $response['choices'][0]['text'] ?? '';
            } else {
                $content = $response->choices[0]->text ?? '';
            }

            // Display the response
            $io->writeln('🤖 <fg=green>Completion:</> '.$content);

            // Display detailed information if requested
            if ($detailed) {
                $io->writeln('');
                $io->writeln('<fg=yellow>Response Details:</>');
                $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
            }

            $this->results['text_completion'] = true;
        } catch (\Exception $e) {
            $this->results['text_completion'] = false;
            $io->error('Error: '.$e->getMessage());
        }
    }

    /**
     * Tests the embeddings endpoint
     */
    private function testEmbeddings(SymfonyStyle $io, LMStudioClientInterface $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Embeddings');

        try {
            $text = 'This is a sample text to create embeddings for.';

            // Display the text
            $io->writeln('🧑‍💻 <fg=blue>Text:</> '.$text);

            // Use a specific embedding model
            $embeddingModel = 'text-embedding-nomic-embed-text-v1.5';
            $io->note("Using embedding model: {$embeddingModel} instead of {$model}");

            // Create a request object
            $apiVersion = $client->getApiVersionNamespace();
            $requestClass = "\\Shelfwood\\LMStudio\\Http\\Requests\\{$apiVersion}\\EmbeddingRequest";
            $request = new $requestClass;
            $request->setInput($text);
            $request->setModel($embeddingModel);

            $response = $client->createEmbeddings($request);

            // Check if the response contains an error
            if (is_array($response) && isset($response['error'])) {
                throw new \Exception($response['error']);
            }

            // Extract embedding dimensions
            $dimensions = 0;

            if (is_array($response) && isset($response['data'][0]['embedding'])) {
                $dimensions = count($response['data'][0]['embedding']);
            } elseif (is_object($response) && isset($response->data[0]->embedding)) {
                $dimensions = count($response->data[0]->embedding);
            }

            $io->writeln('🤖 <fg=green>Embedding:</> Created embedding with '.$dimensions.' dimensions');

            // Display detailed information if requested
            if ($detailed) {
                $io->writeln('');
                $io->writeln('<fg=yellow>Response Details:</>');
                $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
            }

            $this->results['embeddings'] = true;
        } catch (\Exception $e) {
            $this->results['embeddings'] = false;
            $io->error('Error: '.$e->getMessage());
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
     * Tests the structured output feature
     */
    private function testStructuredOutput(SymfonyStyle $io, LMStudioClientInterface $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Structured Output');

        try {
            // Create a chat history
            $history = new ChatHistory([
                Message::system('You are a helpful assistant that provides information in structured format.'),
                Message::user('Generate a recipe for chocolate chip cookies. Include name, ingredients, and steps.'),
            ]);

            // Display the conversation
            $io->writeln('💻 <fg=yellow>System:</> You are a helpful assistant that provides information in structured format.');
            $io->writeln('🧑‍💻 <fg=blue>User:</> Generate a recipe for chocolate chip cookies. Include name, ingredients, and steps.');

            // Define the JSON schema for the response
            $schema = [
                'type' => 'object',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                        'description' => 'The name of the recipe',
                    ],
                    'ingredients' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                        'description' => 'List of ingredients needed',
                    ],
                    'steps' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                        'description' => 'Step by step instructions',
                    ],
                ],
                'required' => ['name', 'ingredients', 'steps'],
            ];

            // Create a request object based on the API type
            $requestClass = $client === $this->lmstudio->openai()
                ? V1ChatCompletionRequest::class
                : V0ChatCompletionRequest::class;

            $request = new $requestClass($history, $model);
            $request = $request
                ->withResponseFormat($schema)
                ->withTemperature(0.7);

            // Get a completion
            $response = $client->chatCompletion($request);

            // Display the response
            $content = $response->choices[0]->message->content ?? '';

            $io->writeln('🤖 <fg=green>Assistant (structured output):</>');

            // Try to parse the JSON response
            $jsonData = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                // Format the JSON for better readability
                $formattedJson = json_encode($jsonData, JSON_PRETTY_PRINT);
                $io->writeln('<fg=green>'.$formattedJson.'</>');

                // Display the recipe in a more readable format
                if (isset($jsonData['name'])) {
                    $io->newLine();
                    $io->writeln('📝 <fg=yellow>Recipe: '.$jsonData['name'].'</>');

                    if (isset($jsonData['ingredients']) && is_array($jsonData['ingredients'])) {
                        $io->newLine();
                        $io->writeln('🥣 <fg=yellow>Ingredients:</>');

                        foreach ($jsonData['ingredients'] as $ingredient) {
                            $io->writeln('  • '.$ingredient);
                        }
                    }

                    if (isset($jsonData['steps']) && is_array($jsonData['steps'])) {
                        $io->newLine();
                        $io->writeln('👨‍🍳 <fg=yellow>Instructions:</>');

                        foreach ($jsonData['steps'] as $index => $step) {
                            $io->writeln('  '.($index + 1).'. '.$step);
                        }
                    }
                }
            } else {
                // If not valid JSON, just display the raw content
                $io->writeln($content);
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

            $this->results['Structured Output'] = [
                'status' => 'Success',
                'message' => 'Successfully received structured output response',
            ];

            $io->success('Successfully tested structured output');
        } catch (\Exception $e) {
            $this->results['Structured Output'] = [
                'status' => 'Failed',
                'message' => $e->getMessage(),
            ];

            $io->error('Failed structured output test: '.$e->getMessage());
        }
    }

    /**
     * Tests the model options features (JIT loading and TTL)
     */
    private function testModelOptions(SymfonyStyle $io, LMStudioClientInterface $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Model Options (JIT & TTL)');

        try {
            // Create a chat history
            $history = new ChatHistory([
                Message::system('You are a helpful assistant.'),
                Message::user('Explain what JIT loading and TTL mean in the context of LLM serving.'),
            ]);

            // Display the conversation
            $io->writeln('💻 <fg=yellow>System:</> You are a helpful assistant.');
            $io->writeln('🧑‍💻 <fg=blue>User:</> Explain what JIT loading and TTL mean in the context of LLM serving.');

            // Create a request object based on the API type
            $requestClass = $client === $this->lmstudio->openai()
                ? V1ChatCompletionRequest::class
                : V0ChatCompletionRequest::class;

            $request = new $requestClass($history, $model);
            $request = $request
                ->withJit(true)  // Enable JIT loading
                ->withTtl(300)   // Set TTL to 5 minutes (300 seconds)
                ->withTemperature(0.7);

            $io->note('Using JIT loading: true, TTL: 300 seconds');

            // Get a completion
            $response = $client->chatCompletion($request);

            // Display the response
            $content = $response->choices[0]->message->content ?? '';
            $io->writeln('🤖 <fg=green>Assistant:</>');
            $io->writeln($content);

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

            $this->results['Model Options'] = [
                'status' => 'Success',
                'message' => 'Successfully used JIT loading and TTL options',
            ];

            $io->success('Successfully tested model options');
        } catch (\Exception $e) {
            $this->results['Model Options'] = [
                'status' => 'Failed',
                'message' => $e->getMessage(),
            ];

            $io->error('Failed model options test: '.$e->getMessage());
        }
    }

    /**
     * Tests the model-specific information endpoint
     */
    private function testModelInfo(SymfonyStyle $io, LMStudioClientInterface $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Model Information');

        try {
            $io->writeln('🔍 <fg=blue>Retrieving information for model:</> '.$model);

            // Get model information
            $modelInfo = $client->model($model);

            // Check if the response contains an error
            if (is_array($modelInfo) && isset($modelInfo['error'])) {
                throw new \Exception($modelInfo['error']);
            }

            // Display the model information
            $io->writeln('📊 <fg=green>Model Information:</>');

            if (is_array($modelInfo)) {
                // Format the model info for display
                $table = new Table($io);
                $table->setHeaders(['Property', 'Value']);

                // Add model ID
                if (isset($modelInfo['id'])) {
                    $table->addRow(['ID', $modelInfo['id']]);
                }

                // Add model object type
                if (isset($modelInfo['object'])) {
                    $table->addRow(['Object Type', $modelInfo['object']]);
                }

                // Add created timestamp
                if (isset($modelInfo['created'])) {
                    $date = new \DateTime('@'.$modelInfo['created']);
                    $table->addRow(['Created', $date->format('Y-m-d H:i:s')]);
                }

                // Add owned by
                if (isset($modelInfo['owned_by'])) {
                    $table->addRow(['Owned By', $modelInfo['owned_by']]);
                }

                // Add root model
                if (isset($modelInfo['root'])) {
                    $table->addRow(['Root Model', $modelInfo['root']]);
                }

                // Render the table
                $table->render();

                // If detailed is enabled, show the full JSON
                if ($detailed) {
                    $io->writeln('<info>Detailed Model Information:</info>');
                    $encoded = json_encode($modelInfo, JSON_PRETTY_PRINT);
                    $io->writeln($encoded !== false ? $encoded : 'Error encoding model info');
                }
            } else {
                $io->writeln('Unable to format model information.');
            }

            $this->results['Model Information'] = [
                'status' => 'Success',
                'message' => 'Successfully retrieved model information',
            ];

            $io->success('Successfully tested model information endpoint');
        } catch (\Exception $e) {
            $this->results['Model Information'] = [
                'status' => 'Failed',
                'message' => $e->getMessage(),
            ];

            $io->error('Failed model information test: '.$e->getMessage());
        }
    }

    /**
     * Tests the Conversation class
     */
    private function testConversationClass(SymfonyStyle $io, LMStudioClientInterface $client, string $model, bool $detailed): void
    {
        $io->section('Testing: Conversation Class');

        try {
            // Create a conversation manager
            $conversationManager = new \Shelfwood\LMStudio\Conversations\ConversationManager($client);

            // Create a conversation with a system message
            $conversation = $conversationManager->createConversationWithSystem(
                'You are a helpful assistant that responds in a concise manner.',
                'Test Conversation'
            );

            // Set the model
            $conversation->setModel($model);

            // Add a user message
            $conversation->addUserMessage('Hello, can you tell me what day it is today?');

            // Get a response
            $response = $conversation->getResponse();

            // Display the response
            $io->writeln('🧑‍💻 <fg=blue>User:</> Hello, can you tell me what day it is today?');
            $io->writeln('🤖 <fg=green>Assistant:</> '.$response);

            // Add another user message
            $conversation->addUserMessage('Thank you. Can you also tell me what LM Studio is?');

            // Get another response
            $response = $conversation->getResponse();

            // Display the response
            $io->writeln('🧑‍💻 <fg=blue>User:</> Thank you. Can you also tell me what LM Studio is?');
            $io->writeln('🤖 <fg=green>Assistant:</> '.$response);

            // Test serialization
            $json = $conversation->toJson();
            $loadedConversation = $conversationManager->loadConversation($json);

            // Verify the loaded conversation
            $io->writeln('Conversation serialization and deserialization: '.
                ($loadedConversation->getTitle() === 'Test Conversation' ? 'Success' : 'Failed'));

            $this->results['Conversation Class'] = [
                'status' => 'Success',
                'message' => 'Successfully created and used a conversation',
            ];

            $io->success('Successfully tested conversation class');
        } catch (\Exception $e) {
            $this->results['Conversation Class'] = [
                'status' => 'Failed',
                'message' => $e->getMessage(),
            ];

            $io->error('Failed to test conversation class: '.$e->getMessage());
        }
    }

    /**
     * Tests the StreamBuilder class
     */
    private function testStreamBuilder(SymfonyStyle $io, LMStudioClientInterface $client, string $model, bool $detailed): void
    {
        $io->section('Testing: StreamBuilder');

        try {
            // Create a conversation manager
            $conversationManager = new \Shelfwood\LMStudio\Conversations\ConversationManager($client);

            // Create a conversation with a system message
            $conversation = $conversationManager->createConversationWithSystem(
                'You are a helpful assistant that responds in a concise manner.',
                'Stream Test'
            );

            // Set the model
            $conversation->setModel($model);

            // Add a user message
            $conversation->addUserMessage('Write a short poem about artificial intelligence.');

            // Create a stream builder
            $streamBuilder = new \Shelfwood\LMStudio\Builders\StreamBuilder($client);
            $streamBuilder
                ->withHistory($conversation->getHistory())
                ->withModel($model)
                ->withTemperature(0.7)
                ->withMaxTokens(150);

            // Collect the response
            $fullResponse = '';
            $io->writeln('🧑‍💻 <fg=blue>User:</> Write a short poem about artificial intelligence.');
            $io->write('🤖 <fg=green>Assistant:</> ');

            // Stream the response
            $streamBuilder->stream(function ($chunk) use ($io, &$fullResponse): void {
                if ($chunk->hasContent()) {
                    $content = $chunk->getContent();
                    $fullResponse .= $content;
                    $io->write($content);
                }
            });

            // Execute the stream
            $streamBuilder->execute();
            $io->newLine(2);

            if ($detailed) {
                $io->writeln('Full response length: '.strlen($fullResponse).' characters');
            }

            $this->results['StreamBuilder'] = [
                'status' => 'Success',
                'message' => 'Successfully used StreamBuilder',
            ];

            $io->success('Successfully tested StreamBuilder');
        } catch (\Exception $e) {
            $this->results['StreamBuilder'] = [
                'status' => 'Failed',
                'message' => $e->getMessage(),
            ];

            $io->error('Failed to test StreamBuilder: '.$e->getMessage());
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
