<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Console\Command;

use Shelfwood\LMStudio\Api\Enum\ResponseFormatType;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\ResponseFormat;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Api\Service\CompletionService;
use Shelfwood\LMStudio\Api\Service\EmbeddingService;
use Shelfwood\LMStudio\Api\Service\ModelService;
use Shelfwood\LMStudio\LMStudioFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class SequenceCommand extends BaseCommand
{
    private LMStudioFactory $factory;

    private array $stepStats = [];

    private array $stepStartTimes = [];

    private ChatService $chatService;

    private CompletionService $completionService;

    private ModelService $modelService;

    private EmbeddingService $embeddingService;

    private string $modelId;

    private string $embeddingModel;

    public function __construct(LMStudioFactory $factory)
    {
        parent::__construct('sequence');

        $this->factory = $factory;
        $this->chatService = $factory->createChatService();
        $this->completionService = $factory->createCompletionService();

        $this->setDescription('Run a sequence of API calls to demonstrate all LM Studio API endpoints')
            ->addOption(
                'model',
                null,
                InputOption::VALUE_OPTIONAL,
                'The model to use (defaults to config value)'
            )
            ->addOption(
                'embedding-model',
                null,
                InputOption::VALUE_OPTIONAL,
                'The embedding model to use'
            );
    }

    private function startStep(string $step): void
    {
        $this->stepStartTimes[$step] = microtime(true);
        $this->info("\nStep {$step}");
    }

    private function endStep(string $step, string $status, ?array $metrics = null): void
    {
        $duration = microtime(true) - ($this->stepStartTimes[$step] ?? microtime(true));
        $this->stepStats[$step] = array_merge([
            'duration' => round($duration, 2),
            'status' => $status,
        ], $metrics ?? []);
    }

    private function showSummary(): void
    {
        $this->info("\n📊 Sequence Summary:");
        $rows = [];

        foreach ($this->stepStats as $step => $stats) {
            $rows[] = [
                $step,
                sprintf('%.2fs', $stats['duration']),
                $stats['tokens'] ?? 'N/A',
                $stats['status'],
                $stats['details'] ?? '',
            ];
        }

        // Create a formatted table string
        $headers = ['Step', 'Duration', 'Tokens', 'Status', 'Details'];
        $this->renderTable($headers, $rows);
    }

    private function renderTable(array $headers, array $rows): void
    {
        // Calculate column widths
        $widths = array_fill(0, count($headers), 0);

        foreach ($headers as $i => $header) {
            $widths[$i] = max($widths[$i], strlen((string) $header));
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], strlen((string) $cell));
            }
        }

        // Print headers
        $this->line('+'.implode('+', array_map(fn ($w) => str_repeat('-', $w + 2), $widths)).'+');
        $headerLine = '|';

        foreach ($headers as $i => $header) {
            $headerLine .= ' '.str_pad((string) $header, $widths[$i]).' |';
        }
        $this->line($headerLine);
        $this->line('+'.implode('+', array_map(fn ($w) => str_repeat('-', $w + 2), $widths)).'+');

        // Print rows
        foreach ($rows as $row) {
            $rowLine = '|';

            foreach ($row as $i => $cell) {
                $rowLine .= ' '.str_pad((string) $cell, $widths[$i]).' |';
            }
            $this->line($rowLine);
        }
        $this->line('+'.implode('+', array_map(fn ($w) => str_repeat('-', $w + 2), $widths)).'+');
    }

    protected function handle(): int
    {
        $this->info('Starting LM Studio API sequence demonstration');

        try {
            $this->setupModels();
            $this->runModelTests();
            $this->runChatTests();
            $this->runToolTests();
            $this->runStructuredOutputTest();
            $this->runCompletionTest();
            $this->runEmbeddingTest();
            $this->showSummary();

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Set up model services and get model IDs.
     */
    private function setupModels(): void
    {
        // Get services
        $this->modelService = $this->factory->createModelService();
        $this->embeddingService = $this->factory->createEmbeddingService();

        // Get models
        $this->modelId = $this->option('model') ?: getenv('LMSTUDIO_DEFAULT_MODEL') ?: 'qwen2.5-7b-instruct';
        $this->embeddingModel = $this->option('embedding-model') ?: getenv('LMSTUDIO_DEFAULT_EMBEDDING_MODEL') ?: 'text-embedding-nomic-embed-text-v1.5';

        $this->info("Using model: {$this->modelId}");
        $this->info("Using embedding model: {$this->embeddingModel}\n");
    }

    /**
     * Run model-related tests (Steps 1-2).
     */
    private function runModelTests(): void
    {
        // Step 1: List models
        $this->startStep('1: Listing available models');
        $modelResponse = $this->modelService->listModels();
        $models = $modelResponse->getModels();
        $this->info("\nAvailable models:");

        foreach ($models as $model) {
            $this->line(" - {$model->getId()} ({$model->getState()->value})");
        }
        $this->endStep('1', '✓', [
            'details' => sprintf('%d models found', count($models)),
        ]);

        // Step 2: Get model info
        $this->startStep('2: Getting model info');
        $modelInfo = $this->modelService->getModel($this->modelId);
        $this->info("\nModel details:");
        $this->line(" - ID: {$modelInfo->getId()}");
        $this->line(" - Type: {$modelInfo->getType()->value}");
        $this->line(" - State: {$modelInfo->getState()->value}");
        $this->line(" - Max context: {$modelInfo->getMaxContextLength()}");
        $this->endStep('2', '✓', [
            'details' => 'Model info retrieved successfully',
        ]);
    }

    /**
     * Run basic chat completion tests (Steps 3-31).
     */
    private function runChatTests(): void
    {
        // Step 3: Basic chat completion
        $this->startStep('3: Basic chat completion (non-streaming)');
        $messages = [
            new Message(Role::SYSTEM, 'You are a helpful assistant.'),
            new Message(Role::USER, 'What is the capital of France?'),
        ];
        /** @var ChatCompletionResponse $response */
        $response = $this->chatService->createCompletion($this->modelId, $messages);
        $this->info("\nResponse: {$response->getContent()}");
        $this->endStep('3', '✓', [
            'tokens' => $response->usage->totalTokens,
            'details' => 'Chat completion successful',
        ]);

        // Step 31: Basic chat completion (streaming)
        $this->startStep('31: Basic chat completion (streaming)');
        $fullContent = '';
        $this->chatService->createCompletionStream(
            $this->modelId,
            $messages,
            function ($chunk) use (&$fullContent): void {
                if (isset($chunk['choices'][0]['delta']['content'])) {
                    $content = $chunk['choices'][0]['delta']['content'];
                    $this->output->write($content);
                    $fullContent .= $content;
                }
            }
        );
        $this->endStep('31', '✓', [
            'details' => 'Streaming chat completion successful',
        ]);
    }

    /**
     * Run tool-based chat completion tests (Steps 4-41).
     */
    private function runToolTests(): void
    {
        // Step 4: Chat completion with tools (non-streaming)
        $this->startStep('4: Chat completion with tools (non-streaming)');

        // Create a conversation builder
        $builder = $this->factory->createConversationBuilder($this->modelId)
            ->withTool(
                'get_current_time',
                function () {
                    return date('Y-m-d H:i:s');
                },
                [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
                'Get the current server time. Use this tool whenever asked about the current time.'
            )
            ->onToolCall(function ($event): void {
                $this->info("\n🔧 Tool called: {$event['name']}");
                $this->line('   Arguments: '.json_encode($event['arguments']));
            })
            ->onResponse(function ($response): void {
                $this->info("\n📝 Response received");
                $this->line("   Tokens: {$response->usage->totalTokens}");
            })
            ->onError(function ($error): void {
                $this->error("\n❌ Error: {$error->getMessage()}");
            });

        // Build and use the conversation
        $conversation = $builder->build();
        $conversation->addSystemMessage('You are a helpful assistant. You MUST use the get_current_time tool whenever asked about time. Do not try to explain that you cannot access the current time - you have a tool for that. Always use the tool and provide the exact time.');
        $conversation->addUserMessage('What is the current time? Please also tell me what you can do with this time information.');
        $response = $conversation->getResponse();
        $this->info("\nResponse: ".$response);

        $this->endStep('4', '✓', [
            'details' => 'Chat completion with tools successful',
        ]);

        // Step 41: Chat completion with tools (streaming)
        $this->startStep('41: Chat completion with tools (streaming)');

        // Create a streaming conversation builder
        $streamingBuilder = $this->factory->createConversationBuilder($this->modelId)
            ->withStreaming()
            ->withTool(
                'get_current_time',
                function () {
                    return date('Y-m-d H:i:s');
                },
                [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
                'Get the current server time. Use this tool whenever asked about the current time.'
            )
            ->onStreamStart(function (): void {
                $this->info("\n🌊 Streaming started");
            })
            ->onStreamContent(function ($content): void {
                $this->output->write($content);
            })
            ->onStreamToolCall(function ($toolCall, $index): void {
                $this->info("\n🔧 Tool call received for index {$index}");
                $this->line("   Name: {$toolCall['function']['name']}");
                $this->line("   Arguments: {$toolCall['function']['arguments']}");
            })
            ->onStreamEnd(function (): void {
                $this->info("\n🏁 Streaming ended");
            })
            ->onStreamError(function ($error): void {
                $this->error("\n❌ Streaming error: {$error->getMessage()}");
            });

        // Build and use the streaming conversation
        $streamingConversation = $streamingBuilder->build();
        $streamingConversation->addSystemMessage('You are a helpful assistant. You MUST use the get_current_time tool whenever asked about time. Do not try to explain that you cannot access the current time - you have a tool for that. Always use the tool and provide the exact time.');
        $streamingConversation->addUserMessage('What time will it be in 2 hours from now? Please use the current time to calculate this.');
        $streamingConversation->getStreamingResponse();

        $this->endStep('41', '✓', [
            'details' => 'Streaming chat completion with tools successful',
        ]);
    }

    /**
     * Run structured output test (Step 5).
     */
    private function runStructuredOutputTest(): void
    {
        $this->startStep('5: Chat completion with structured output');
        $messages = [
            new Message(Role::SYSTEM, 'You are a helpful assistant that always responds in valid JSON format according to the provided schema. Do not include any explanatory text outside the JSON. The response MUST use "city_name" (not "city") for the city name field.'),
            new Message(Role::USER, 'Give me information about Paris.'),
        ];

        $jsonSchema = [
            'type' => 'object',
            'properties' => [
                'city_name' => [
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
                'notable_features' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                    'description' => 'Famous landmarks and features in the city',
                ],
                'famous_for' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                    'description' => 'What the city is famous for',
                ],
            ],
            'required' => ['city_name', 'country', 'notable_features'],
        ];

        $responseFormat = new ResponseFormat(ResponseFormatType::JSON_SCHEMA, $jsonSchema);
        $response = $this->chatService->createCompletion($this->modelId, $messages, [
            'response_format' => $responseFormat,
        ]);

        $this->info('Structured response:');
        $this->line($response->getContent());

        // Pretty print the JSON
        $jsonData = json_decode($response->getContent(), true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $this->info('Parsed data:');
            $this->line(' - City: '.($jsonData['city_name'] ?? 'N/A'));
            $this->line(' - Country: '.($jsonData['country'] ?? 'N/A'));

            if (isset($jsonData['population'])) {
                $this->line(" - Population: {$jsonData['population']}");
            }

            if (isset($jsonData['notable_features']) && is_array($jsonData['notable_features'])) {
                $this->line(' - Notable Features: '.implode(', ', $jsonData['notable_features']));
            } else {
                $this->line(' - Notable Features: N/A');
            }

            if (isset($jsonData['famous_for']) && is_array($jsonData['famous_for'])) {
                $this->line(' - Famous For: '.implode(', ', $jsonData['famous_for']));
            }
        }

        $this->endStep('5', '✓', [
            'tokens' => $response->getUsage()->totalTokens,
            'details' => 'Structured output chat completion successful',
        ]);
    }

    /**
     * Run text completion test (Step 6).
     */
    private function runCompletionTest(): void
    {
        $this->startStep('6: Text completion');
        $prompt = 'The capital of France is';

        $response = $this->completionService->createCompletion($this->modelId, $prompt, [
            'max_tokens' => 10,
        ]);

        $this->info('Prompt: '.$prompt);
        $this->info('Completion: '.$response->getChoices()[0]['text']);
        $this->endStep('6', '✓', [
            'tokens' => $response->usage['total_tokens'] ?? 0,
            'details' => 'Text completion successful',
        ]);
    }

    /**
     * Run embedding test (Step 7).
     */
    private function runEmbeddingTest(): void
    {
        $this->startStep('7: Embeddings');
        $text = 'The quick brown fox jumps over the lazy dog.';

        $response = $this->embeddingService->createEmbedding($this->embeddingModel, $text);

        // Get the first embedding from the data array
        $embedding = $response->data[0]['embedding'] ?? [];
        $dimensions = count($embedding);

        $this->info('Text: '.$text);
        $this->info("Embedding dimensions: $dimensions");
        $this->info('First 5 values: '.implode(', ', array_slice($embedding, 0, 5)));
        $this->endStep('7', '✓', [
            'details' => 'Embedding generation successful',
        ]);
    }
}
