<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Console\Command;

use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Core\Manager\ConversationManager;
use Shelfwood\LMStudio\LMStudioFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class SequenceCommand extends BaseCommand
{
    private readonly LMStudioFactory $factory;

    /** @var array<string, array{duration: float, status: string, tokens?: int|string, details?: string}> */
    private array $stepStats = [];

    /** @var array<string, float> */
    private array $stepStartTimes = [];

    private ?\Shelfwood\LMStudio\Api\Service\ModelService $modelService = null;

    private ?\Shelfwood\LMStudio\Api\Service\EmbeddingService $embeddingService = null;

    private string $modelId;

    private string $embeddingModel;

    private ?ConversationManager $conversation = null;

    private ?string $currentStepTitle = null;

    public function __construct(LMStudioFactory $factory)
    {
        parent::__construct('sequence');

        $this->factory = $factory;

        $this->setDescription('Run a sequence of API calls to demonstrate LM Studio API endpoints')
            ->addOption(
                'model',
                null,
                InputOption::VALUE_OPTIONAL,
                'The model to use'
            )
            ->addOption(
                'embedding-model',
                null,
                InputOption::VALUE_OPTIONAL,
                'The embedding model'
            );
    }

    private function startStep(string $title): void
    {
        $this->stepStartTimes[$title] = microtime(true);
        $this->info("\n--- {$title} ---");
    }

    /**
     * @param  array<string, mixed>|null  $metrics
     */
    private function endStep(string $title, string $status, ?array $metrics = null): void
    {
        $duration = microtime(true) - ($this->stepStartTimes[$title] ?? microtime(true));
        $this->stepStats[$title] = array_merge([
            'duration' => round($duration, 2),
            'status' => $status,
        ], $metrics ?? []);
        $this->info("✓ Step finished ({$status}) - Duration: ".sprintf('%.2fs', $duration));
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

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float>>  $rows
     */
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

            // Sequence of tests
            $this->executeSequenceStep('List Models', [$this, 'runListModels']);
            $this->executeSequenceStep('Get Model Info', [$this, 'runGetModelInfo']);
            $this->executeSequenceStep('Basic Chat Completion', [$this, 'runBasicChat']);
            $this->executeSequenceStep('Basic Chat Completion (Streaming)', [$this, 'runBasicChatStreaming']);
            $this->executeSequenceStep('Chat Completion with Tools', [$this, 'runChatWithTools']);
            $this->executeSequenceStep('Chat Completion with Tools (Streaming)', [$this, 'runChatWithToolsStreaming']);
            // $this->executeSequenceStep('Structured Output (JSON Schema)', [$this, 'runStructuredOutputTest']);
            // $this->executeSequenceStep('Text Completion', [$this, 'runCompletionTest']);
            // $this->executeSequenceStep('Text Embedding', [$this, 'runEmbeddingTest']);

            $this->showSummary(); // Optional summary table

            return Command::SUCCESS;
        } catch (Throwable $e) { // Catch Throwable for broader coverage
            $this->error("\n🚨 Sequence failed during step: ".($this->currentStepTitle ?? 'Setup'));
            $this->error('   Error: '.$e->getMessage());

            if ($this->output->isVerbose()) {
                $this->line("\n".$e->getTraceAsString()); // Print trace for debugging
            }

            return Command::FAILURE;
        }
    }

    /**
     * Helper to run a step with start/end logging and error handling.
     */
    private function executeSequenceStep(string $title, callable $callback): void
    {
        $this->currentStepTitle = $title;
        $this->startStep($title);
        $metrics = [];
        $status = '✓';

        try {
            $result = $callback();

            if (is_array($result)) {
                $metrics = $result;
            }
        } catch (Throwable $e) {
            $status = '❌ Error';

            throw $e;
        } finally {
            $this->endStep($title, $status, $metrics);
            $this->currentStepTitle = null;
        }
    }

    /**
     * Set up model services and get model IDs.
     */
    private function setupModels(): void
    {
        // Get services
        $this->modelService = $this->factory->getModelService();
        $this->embeddingService = $this->factory->getEmbeddingService();

        // Fetch options allowing null, then apply fallbacks
        $modelOption = $this->input->getOption('model'); // Can be string or null
        $embeddingOption = $this->input->getOption('embedding-model'); // Can be string or null

        $this->modelId = $modelOption ?? getenv('LMSTUDIO_DEFAULT_MODEL') ?: 'qwen2.5-7b-instruct';
        $this->embeddingModel = $embeddingOption ?? getenv('LMSTUDIO_DEFAULT_EMBEDDING_MODEL') ?: 'text-embedding-nomic-embed-text-v1.5';

        $this->info("Using model: {$this->modelId}");
        $this->info("Using embedding model: {$this->embeddingModel}\n");
    }

    // --- Individual Step Methods ---

    private function runListModels(): array
    {
        $modelResponse = $this->modelService->listModels();
        $models = $modelResponse->getModels();
        $this->info('Available models:');

        foreach ($models as $model) {
            $this->line(" - {$model->id} ({$model->state->value})");
        }

        return ['details' => sprintf('%d models found', count($models))];
    }

    private function runGetModelInfo(): array
    {
        $modelInfo = $this->modelService->getModel($this->modelId);
        $this->info("Model details for '{$this->modelId}':");
        $this->line(" - Type: {$modelInfo->type->value}");
        $this->line(" - State: {$modelInfo->state->value}");
        $this->line(" - Max context: {$modelInfo->maxContextLength}");

        return ['details' => 'Model info retrieved'];
    }

    private function runBasicChat(): array
    {
        $this->conversation = $this->factory->createConversationBuilder($this->modelId)->build();

        $this->conversation->addSystemMessage('You are a helpful assistant.');
        $this->conversation->addUserMessage('What is the capital of France?');
        $responseContent = $this->conversation->getResponse();
        $this->info("Response: {$responseContent}");

        return ['details' => 'Chat completion successful'];
    }

    private function runBasicChatStreaming(): array
    {
        $this->conversation = $this->factory->createConversationBuilder($this->modelId)->withStreaming(true)->build();

        $this->conversation->addSystemMessage('You are a helpful streaming assistant.');
        $this->conversation->addUserMessage('Tell me a short story about a robot who learns to paint.');

        $fullResponse = '';
        $this->info('Streaming response:');
        $this->output->write('  ↪ '); // Start output line

        // Register listener for content chunks
        $this->conversation->onStreamContent(function (string $content) use (&$fullResponse): void {
            $this->output->write($content);
            $fullResponse .= $content;
        });

        // Listener for end to add a newline
        $this->conversation->onStreamEnd(function (): void {
            // Don't add newline here, do it after checking final content
        });

        // Listener for stream errors
        $this->conversation->onStreamError(function (Throwable $e): void {
            $this->error("\n   [STREAM ERROR]: ".$e->getMessage());
        });

        // Execute the streaming turn (listeners will fire during this)
        $finalContent = $this->conversation->handleStreamingTurn();

        // Check if final content differs significantly from streamed content or if stream was empty
        if (trim($fullResponse) === '' || trim($finalContent) !== trim($fullResponse)) {
            // If stream was empty or final content is different, print final content clearly
            if (trim($fullResponse) === '') {
                $this->output->writeln('');
            } // Ensure newline if stream was empty
            $this->info("\n   [Final Assembled Content]: ".trim($finalContent));
        } else {
            // Otherwise, just ensure the line is ended if content was streamed
            $this->output->writeln('');
        }

        return ['details' => 'Streaming chat successful'];
    }

    private function runChatWithTools(): array
    {
        $this->conversation = $this->factory->createConversationBuilder($this->modelId)
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

        $this->conversation->addSystemMessage('You are an assistant that can use tools.');
        $this->conversation->addUserMessage('What time is it right now?');

        $responseContent = $this->conversation->getResponse();

        $this->info("Final Response: {$responseContent}");

        $toolUsed = false;

        foreach ($this->conversation->getMessages() as $message) {
            if ($message->role === Role::TOOL && $message->toolCallId) {
                $toolUsed = true;

                break;
            }
        }

        return ['details' => 'Tool chat successful'.($toolUsed ? ' (tool was called)' : ' (tool not called)')];
    }

    private function runChatWithToolsStreaming(): array
    {
        $this->conversation = $this->factory->createConversationBuilder($this->modelId)
            ->withTool(
                'get_weather',
                function (array $args): array {
                    $location = $args['location'] ?? 'unknown';
                    $this->info("\n   [Tool Executing: get_weather for '{$location}']");
                    $weather = 'sunny';

                    if (stripos($location, 'london') !== false) {
                        $weather = 'rainy';
                    }

                    if (stripos($location, 'paris') !== false) {
                        $weather = 'cloudy';
                    }
                    sleep(1); // Simulate network delay

                    return ['temperature' => rand(10, 25), 'conditions' => $weather];
                },
                [
                    'type' => 'object',
                    'properties' => [
                        'location' => ['type' => 'string', 'description' => 'The city name'],
                    ],
                    'required' => ['location'],
                ],
                'Gets the current weather for a location.'
            )
            ->withStreaming(true)
            ->build();

        $this->conversation->addSystemMessage('You are a helpful weather assistant with tools.');
        $this->conversation->addUserMessage('What is the weather like in London?');

        $fullResponse = '';
        $toolCalledDuringStream = false; // Renamed for clarity
        $toolExecutionResult = null; // Variable to store result/error

        $this->info('Streaming response:');
        $this->output->write('  ↪ '); // Start output line

        // Register listener for content chunks
        $this->conversation->onStreamContent(function (string $content) use (&$fullResponse): void {
            $this->output->write($content);
            $fullResponse .= $content;
        });

        // Listener for tool call start
        $this->conversation->onStreamToolCallStart(function (string $toolCallId, string $toolName) use (&$toolCalledDuringStream): void {
            $this->output->writeln("\n   [Tool Call Detected: ".($toolName ?: '<EMPTY_NAME>').'] [...processing...]'); // Show if name was empty
            $toolCalledDuringStream = true;
        });

        // Add listener for successful tool execution
        $this->conversation->onToolExecuted(function (string $toolCallId, string $toolName, $result) use (&$toolExecutionResult): void {
            $resultString = is_string($result) ? $result : json_encode($result);
            $this->info('   [Tool Executed: '.($toolName ?: '<EMPTY_NAME>')." -> Result: {$resultString}]");
            $toolExecutionResult = $result;
        });

        // Add listener for tool execution error
        $this->conversation->onToolError(function (string $toolCallId, string $toolName, Throwable $error) use (&$toolExecutionResult): void {
            $this->error('   [Tool Error: '.($toolName ?: '<EMPTY_NAME>')." -> Error: {$error->getMessage()}]");
            $toolExecutionResult = ['error' => $error->getMessage()]; // Store error info
        });

        // Listener for end to add a newline
        $this->conversation->onStreamEnd(function (): void {
            // Don't add newline here
        });

        // Listener for stream errors
        $this->conversation->onStreamError(function (Throwable $e): void {
            $this->error("\n   [STREAM ERROR]: ".$e->getMessage());
        });

        // Execute the streaming turn (listeners will fire during this)
        $finalContent = $this->conversation->handleStreamingTurn();

        // Check if final content differs significantly or if stream was empty
        if (trim($fullResponse) === '' || trim($finalContent) !== trim($fullResponse)) {
            if (trim($fullResponse) === '') {
                $this->output->writeln('');
            } // Ensure newline
            $this->info("\n   [Final Assembled Content]: ".trim($finalContent));
        } else {
            $this->output->writeln(''); // Ensure newline
        }

        // Refine details based on actual execution outcome
        $details = 'Streaming tool chat successful';

        if ($toolExecutionResult !== null) {
            if (isset($toolExecutionResult['error'])) {
                $details .= ' (tool execution FAILED)';
            } else {
                $details .= ' (tool executed successfully)';
            }
        } elseif ($toolCalledDuringStream) {
            // Tool call detected in stream but no execution event fired (shouldn't normally happen)
            $details .= ' (tool call detected but no execution result)';
        } else {
            $details .= ' (tool not called by model)';
        }

        return ['details' => $details];
    }

    // @TODO: Add more steps for other endpoints (Embeddings, Completions, Structured Output)
}
