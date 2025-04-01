<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel\Commands;

use Illuminate\Console\Command;
use Shelfwood\LMStudio\Api\Enum\ResponseFormatType;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Exception\ApiException;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\ResponseFormat;
use Shelfwood\LMStudio\LMStudioFactory;

class SequenceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sequence
                            {--model= : The model to use (defaults to config value)}
                            {--embedding-model= : The embedding model to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a sequence of API calls to demonstrate all LM Studio API endpoints';

    /**
     * Execute the console command.
     */
    public function handle(LMStudioFactory $factory)
    {
        // Get the model from options or config
        $model = $this->option('model') ?: config('lmstudio.default_model', 'qwen2.5-7b-instruct');
        $embeddingModel = $this->option('embedding-model') ?: config('lmstudio.default_embedding_model', 'text-embedding-nomic-embed-text-v1.5');

        $this->info('Starting LM Studio API sequence demonstration');
        $this->info("Using model: $model");
        $this->info("Using embedding model: $embeddingModel");
        $this->newLine();

        // Create services (using the injected factory)
        $modelService = $factory->getModelService();
        $chatService = $factory->getChatService();
        $completionService = $factory->getCompletionService();
        $embeddingService = $factory->getEmbeddingService();

        // Step 1: List models
        $this->runStep(1, 'Listing available models', function () use ($modelService) {
            $modelResponse = $modelService->listModels();
            $models = $modelResponse->getModels();
            $this->info('Available models:');

            foreach ($models as $modelInfo) {
                $this->line(" - {$modelInfo->id} ({$modelInfo->state->value})");
            }

            return count($models).' models found';
        });

        // Step 2: Get model info
        $this->runStep(2, 'Getting model info', function () use ($modelService, $model) {
            $modelInfo = $modelService->getModel($model);
            $this->info('Model details:');
            $this->line(" - ID: {$modelInfo->id}");
            $this->line(" - Type: {$modelInfo->type->value}");
            $this->line(" - State: {$modelInfo->state->value}");
            $this->line(" - Max context: {$modelInfo->maxContextLength}");

            return 'Model info retrieved successfully';
        });

        // Step 3: Basic chat completion
        $this->runStep(3, 'Basic chat completion', function () use ($chatService, $model) {
            $messages = [
                new Message(Role::SYSTEM, 'You are a helpful assistant.'),
                new Message(Role::USER, 'What is the capital of France?'),
            ];

            $response = $chatService->createCompletion($model, $messages);
            $this->info('Response: '.$response->getContent());

            return 'Chat completion successful';
        });

        // Step 4: Chat completion with tools
        $this->runStep(4, 'Chat completion with tools', function () use ($factory, $model) {
            $conversation = $factory->createConversationBuilder($model)
                ->withTool(
                    'get_current_time',
                    fn () => ['time' => date('Y-m-d H:i:s')],
                    [
                        'type' => 'object',
                        'properties' => [],
                    ],
                    'Gets the current server date and time.'
                )
                ->build();

            $conversation->addMessage(new Message(Role::SYSTEM, 'You have access to tools.'));
            $conversation->addMessage(new Message(Role::USER, 'What time is it please?'));

            $finalResponse = $conversation->getResponse();

            $this->info('Final response: '.$finalResponse);

            return 'Tool-based chat completion successful (using ConversationManager)';
        });

        // Step 5: Chat completion with structured output
        $this->runStep(5, 'Chat completion with structured output', function () use ($chatService, $model) {
            $messages = [
                new Message(Role::USER, 'Give me information about Paris.'),
            ];

            $jsonSchema = [
                'name' => 'city_info',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'The name of the city',
                        ],
                        'country' => [
                            'type' => 'string',
                            'description' => 'The country where the city is located',
                        ],
                        'population' => [
                            'type' => 'number',
                            'description' => 'The approximate population of the city',
                        ],
                        'landmarks' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                            'description' => 'Famous landmarks in the city',
                        ],
                    ],
                    'required' => ['name', 'country', 'landmarks'],
                ],
            ];

            $responseFormat = new ResponseFormat(ResponseFormatType::JSON_SCHEMA, $jsonSchema);

            $response = $chatService->createCompletion($model, $messages, [
                'response_format' => $responseFormat,
            ]);

            $this->info('Structured response:');
            $this->line($response->getContent());

            $jsonData = json_decode($response->getContent(), true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->info('Parsed data:');
                $this->line(" - City: {$jsonData['name']}");
                $this->line(" - Country: {$jsonData['country']}");

                if (isset($jsonData['population'])) {
                    $this->line(" - Population: {$jsonData['population']}");
                }
                $this->line(' - Landmarks: '.implode(', ', $jsonData['landmarks']));
            } else {
                $this->warn(' - Failed to parse JSON response.');
            }

            return 'Structured output chat completion successful';
        });

        // Step 6: Text completion
        $this->runStep(6, 'Text completion', function () use ($completionService, $model) {
            $prompt = 'The capital of France is';

            $response = $completionService->createCompletion($model, $prompt, [
                'max_tokens' => 10,
            ]);

            $this->info('Prompt: '.$prompt);
            $this->info('Completion: '.$response->getChoices()[0]['text']);

            return 'Text completion successful';
        });

        // Step 7: Embeddings
        $this->runStep(7, 'Embeddings', function () use ($embeddingService, $embeddingModel) {
            $text = 'The quick brown fox jumps over the lazy dog.';

            $response = $embeddingService->createEmbedding($embeddingModel, $text);

            $embedding = $response->data[0]['embedding'] ?? [];
            $dimensions = count($embedding);

            $this->info('Text: '.$text);
            $this->info("Embedding dimensions: $dimensions");
            $this->info('First 5 values: '.implode(', ', array_slice($embedding, 0, 5)));

            return 'Embedding generation successful';
        });

        // Step 8: Conversation
        $this->runStep(8, 'Conversation', function () use ($factory, $model) {
            $conversation = $factory->createConversationBuilder($model)->build();

            $conversation->addSystemMessage('You are a helpful assistant that responds in a concise manner.');
            $conversation->addUserMessage('What is machine learning?');

            $response1 = $conversation->getResponse();
            $this->info('Response 1: '.$response1);

            $conversation->addUserMessage('Give me an example application.');

            $response2 = $conversation->getResponse();
            $this->info('Response 2: '.$response2);

            return 'Conversation successful (using ConversationManager)';
        });

        $this->newLine();
        $this->info('✅ All API endpoints demonstrated successfully!');

        return Command::SUCCESS;
    }

    protected function runStep(int $number, string $description, callable $callback): void
    {
        $this->newLine();
        $this->components->info("Step $number: $description");
        $this->newLine();

        try {
            $result = $callback();
            $this->newLine();
            $this->components->task($description, true);

            if (is_string($result) && $result) {
                $this->line("  ↪ $result");
            }
        } catch (\Exception $e) {
            $this->newLine();
            $this->components->task($description, false);

            $message = $e->getMessage();

            if ($e instanceof ApiException && $e->getResponse()) {
                $apiResponse = $e->getResponse();

                if (isset($apiResponse['error'])) {
                    $errorData = $apiResponse['error'];

                    if (is_string($errorData)) {
                        $message = $errorData;
                    } elseif (is_array($errorData) && isset($errorData['message'])) {
                        $message = $errorData['message'];
                    } else {
                        $message = json_encode($errorData);
                    }
                }
            }

            $this->error("  ↪ Error: {$message}");

            if (str_contains($message, 'not found') || str_contains($message, 'does not exist')) {
                $this->warn("  ↪ Hint: The model ID might be incorrect or the model isn't loaded. Check available models.");
            } elseif (str_contains($message, 'function') || str_contains($message, 'tool')) {
                $this->warn('  ↪ Hint: This model might not support function/tool calling, or the tool definition is incorrect.');
            } elseif ($e instanceof \GuzzleHttp\Exception\ConnectException || str_contains($message, 'Connection refused')) {
                $this->warn('  ↪ Hint: Could not connect to LM Studio. Is the server running at the configured base URL?');
            }
        }
    }
}
