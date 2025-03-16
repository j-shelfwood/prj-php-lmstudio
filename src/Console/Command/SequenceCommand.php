<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Console\Command;

use Shelfwood\LMStudio\Api\Enum\ResponseFormatType;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Exception\ToolCallException;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\ResponseFormat;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;
use Shelfwood\LMStudio\Api\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Api\Service\CompletionService;
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

    private function createToolBasedChat(): array
    {
        // Create tool parameters
        $parameters = new ToolParameters;

        // Create tool definition
        $toolDefinition = new ToolDefinition(
            'get_current_time',
            'Get the current server time. Use this tool whenever asked about the current time.',
            $parameters
        );

        // Create the tool with proper type
        $timeTool = new Tool(
            ToolType::FUNCTION,
            $toolDefinition
        );

        // Format system message with tool definitions using ChatService
        return [
            $this->chatService->formatSystemMessageWithTools('You are a helpful assistant.', [$timeTool]),
            new Message(Role::USER, 'What is the current time?'),
        ];
    }

    protected function handle(): int
    {
        $this->info('Starting LM Studio API sequence demonstration');

        try {
            // Get services
            $modelService = $this->factory->createModelService();
            $embeddingService = $this->factory->createEmbeddingService();

            // Get models
            $modelId = $this->option('model') ?: getenv('LMSTUDIO_DEFAULT_MODEL') ?: 'qwen2.5-7b-instruct';
            $embeddingModel = $this->option('embedding-model') ?: getenv('LMSTUDIO_DEFAULT_EMBEDDING_MODEL') ?: 'text-embedding-nomic-embed-text-v1.5';

            $this->info("Using model: {$modelId}");
            $this->info("Using embedding model: {$embeddingModel}\n");

            // Step 1: List models
            $this->startStep('1: Listing available models');
            $modelResponse = $modelService->listModels();
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
            $modelInfo = $modelService->getModel($modelId);
            $this->info("\nModel details:");
            $this->line(" - ID: {$modelInfo->getId()}");
            $this->line(" - Type: {$modelInfo->getType()->value}");
            $this->line(" - State: {$modelInfo->getState()->value}");
            $this->line(" - Max context: {$modelInfo->getMaxContextLength()}");
            $this->endStep('2', '✓', [
                'details' => 'Model info retrieved successfully',
            ]);

            // Step 3: Basic chat completion
            $this->startStep('3: Basic chat completion (non-streaming)');
            $messages = [
                new Message(Role::SYSTEM, 'You are a helpful assistant.'),
                new Message(Role::USER, 'What is the capital of France?'),
            ];
            /** @var ChatCompletionResponse $response */
            $response = $this->chatService->createCompletion($modelId, $messages);
            $this->info("\nResponse: {$response->getContent()}");
            $this->endStep('3', '✓', [
                'tokens' => $response->usage->totalTokens,
                'details' => 'Chat completion successful',
            ]);

            // Step 31: Basic chat completion (streaming)
            $this->startStep('31: Basic chat completion (streaming)');
            $fullContent = '';
            $this->chatService->createCompletionStream(
                $modelId,
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

            // Step 4: Chat completion with tools
            $this->startStep('4: Chat completion with tools (non-streaming)');

            $messages = $this->createToolBasedChat();

            // Log tool definition for debugging
            $this->info("\nSending request with tool definition:");
            $this->line(json_encode($messages[0]->toArray(), JSON_PRETTY_PRINT));

            /** @var ChatCompletionResponse $response */
            $response = $this->chatService->createCompletion($modelId, $messages);

            if ($response->getContent()) {
                $this->info("\nResponse content: ".$response->getContent());
            }

            if ($response->hasToolCalls()) {
                $this->info("\nTool calls:");

                foreach ($response->getToolCalls() as $toolCall) {
                    $this->line(json_encode($toolCall, JSON_PRETTY_PRINT));
                }
            }

            $this->endStep('4', '✓', [
                'tokens' => $response->usage->totalTokens,
                'details' => 'Chat completion with tools successful',
            ]);

            // Step 4b: Chat completion with tools (streaming)
            $this->startStep('41: Chat completion with tools (streaming)');
            $messages = [
                new Message(Role::SYSTEM, 'You are a helpful assistant that uses tools when needed. When asked about time, ALWAYS use the get_current_time tool to get the current time. Do not make up times or respond in natural language. You must use the tool.'),
                new Message(Role::USER, 'What time is it?'),
            ];

            // Create tool parameters
            $parameters = new ToolParameters;

            // Create tool definition
            $toolDefinition = new ToolDefinition(
                'get_current_time',
                'Get the current server time. Use this tool whenever asked about the current time.',
                $parameters
            );

            // Create the tool
            $timeTool = new Tool(
                ToolType::FUNCTION,
                $toolDefinition
            );

            $toolCalls = [];
            $fullContent = '';
            $isComplete = false;
            $toolCallsReceived = false;
            $toolCallDeltas = [];

            $this->chatService->createCompletionStream(
                $modelId,
                $messages,
                function ($chunk) use (&$fullContent, &$toolCalls, &$isComplete, &$toolCallsReceived, &$toolCallDeltas, $modelId): void {
                    // Handle content
                    if (isset($chunk['choices'][0]['delta']['content'])) {
                        $content = $chunk['choices'][0]['delta']['content'];
                        $fullContent .= $content;
                        $this->output->write($content);
                    }

                    // Handle tool calls
                    if (isset($chunk['choices'][0]['delta']['tool_calls'])) {
                        $toolCallsReceived = true;

                        foreach ($chunk['choices'][0]['delta']['tool_calls'] as $delta) {
                            $this->output->write('.');
                            $toolCallDeltas[] = $delta;

                            // Enhanced debugging for tool call deltas
                            error_log(sprintf(
                                '[DEBUG] Tool call delta received: %s | Full content so far: %s | All deltas: %s',
                                json_encode($delta, JSON_PRETTY_PRINT),
                                $fullContent,
                                json_encode($toolCallDeltas, JSON_PRETTY_PRINT)
                            ));
                        }
                    }

                    // Check if complete
                    if (isset($chunk['choices'][0]['finish_reason'])) {
                        $isComplete = true;

                        if ($chunk['choices'][0]['finish_reason'] === 'tool_calls') {
                            if (! isset($chunk['choices'][0]['message']['tool_calls'])) {
                                throw ToolCallException::streamingToolCallError(
                                    'Finish reason is "tool_calls" but no tool calls found in message',
                                    $chunk,
                                    [
                                        'model' => $modelId,
                                        'tool_call_deltas' => $toolCallDeltas,
                                        'full_content' => $fullContent,
                                        'raw_chunk' => json_encode($chunk, JSON_PRETTY_PRINT),
                                    ]
                                );
                            }
                            $toolCalls = $chunk['choices'][0]['message']['tool_calls'];

                            // Log complete tool calls for debugging
                            error_log(sprintf(
                                '[DEBUG] Complete tool calls received: %s',
                                json_encode($toolCalls, JSON_PRETTY_PRINT)
                            ));
                        }
                    }
                },
                ['tools' => [$timeTool]]
            );

            $this->endStep('41', '✓', [
                'tokens' => $response->usage->totalTokens,
                'details' => 'Streaming tool-based chat completion successful',
            ]);

            // Step 5: Chat completion with structured output
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

            $response = $this->chatService->createCompletion($modelId, $messages, [
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

            // Step 6: Text completion
            $this->startStep('6: Text completion');
            $prompt = 'The capital of France is';

            $response = $this->completionService->createCompletion($modelId, $prompt, [
                'max_tokens' => 10,
            ]);

            $this->info('Prompt: '.$prompt);
            $this->info('Completion: '.$response->getChoices()[0]['text']);
            $this->endStep('6', '✓', [
                'tokens' => $response->usage['total_tokens'] ?? 0,
                'details' => 'Text completion successful',
            ]);

            // Step 7: Embeddings
            $this->startStep('7: Embeddings');
            $text = 'The quick brown fox jumps over the lazy dog.';

            $response = $embeddingService->createEmbedding($embeddingModel, $text);

            // Get the first embedding from the data array
            $embedding = $response->data[0]['embedding'] ?? [];
            $dimensions = count($embedding);

            $this->info('Text: '.$text);
            $this->info("Embedding dimensions: $dimensions");
            $this->info('First 5 values: '.implode(', ', array_slice($embedding, 0, 5)));
            $this->endStep('7', '✓', [
                'details' => 'Embedding generation successful',
            ]);

            // Show summary at the end
            $this->showSummary();

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
