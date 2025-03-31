<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Console\Command;

use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Core\Conversation\Conversation;
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

    private ?Conversation $conversation = null;

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
            $this->line("\n".$e->getTraceAsString()); // Print trace for debugging

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
        $this->conversation = $this->factory->createConversation($this->modelId);
        $this->conversation->addSystemMessage('You are a helpful assistant.');
        $this->conversation->addUserMessage('What is the capital of France?');
        $responseContent = $this->conversation->getResponse();
        $this->info("Response: {$responseContent}");

        // Assuming usage is attached to the conversation or response somehow if needed
        return ['details' => 'Chat completion successful'];
    }

    /**
     * REFACTORED: Run basic chat completion using streaming.
     */
    private function runBasicChatStreaming(): array
    {
        $this->conversation = $this->factory->createStreamingConversation($this->modelId);
        $this->conversation->addSystemMessage('You are a helpful assistant.');
        $this->conversation->addUserMessage('Describe quantum physics in simple terms.');

        // Access public readonly property for attaching user-defined listeners *before* handling the turn
        $handler = $this->conversation->streamingHandler;

        if ($handler === null) {
            throw new \RuntimeException('Streaming handler not available for streaming conversation.');
        }

        $fullResponse = '';

        // Example: User can still listen for content updates for UI etc.
        $handler->on('stream_content', function (string $content) use (&$fullResponse): void {
            $this->output->write($content);
            $fullResponse .= $content;
        });

        // Add a newline listener for cleaner output in the CLI
        $handler->on('stream_end', fn () => $this->newLine());

        // Handle stream errors if they occur (handleStreamingTurn will also throw)
        $handler->on('stream_error', function (Throwable $e): void {
            $this->error("\nStream error reported: {$e->getMessage()}");
        });

        // --- Use the new simplified method ---
        try {
            // handleStreamingTurn blocks until the turn is complete (including potential tool calls/second response)
            // The user-defined stream_content listener above will still execute during this.
            $finalContent = $this->conversation->handleStreamingTurn();
            // $finalContent contains the *final* textual response after any tool processing.
            // The stream_content listener handled the real-time output already.

            // Note: $fullResponse accumulated by the listener might differ slightly if tools were involved,
            // as it only captured the *initial* stream. $finalContent is the definitive result.
            return ['details' => 'Streaming chat completed.'];
        } catch (Throwable $e) {
            $this->error("\nError during handleStreamingTurn: {$e->getMessage()}");

            // Re-throw or handle as appropriate for the command's workflow
            throw $e;
        }
    }

    private function runChatWithTools(): array
    {
        $this->conversation = $this->factory->createConversation($this->modelId);

        // Access public readonly property
        $toolRegistry = $this->conversation->toolRegistry;

        // Get tools from the factory's pre-configured ToolConfigService
        $configuredTools = $this->factory->toolConfigService->getToolConfigurations();

        foreach ($configuredTools as $name => $config) {
            $toolRegistry->registerTool(
                $name,
                $config['callback'],
                $config['parameters'] ?? [],
                $config['description'] ?? null
            );
        }

        $this->conversation->addSystemMessage('You are a calculator assistant.');
        $this->conversation->addUserMessage('What is 2 + 2?');
        $responseContent = $this->conversation->getResponse(); // This handles the tool call internally
        $this->info("Response: {$responseContent}");

        return ['details' => 'Tool call handled successfully.'];
    }

    /**
     * Run chat completion with tools using streaming.
     */
    private function runChatWithToolsStreaming(): array
    {
        $this->conversation = $this->factory->createStreamingConversation($this->modelId);

        // Access public readonly properties
        $toolRegistry = $this->conversation->toolRegistry;
        $handler = $this->conversation->streamingHandler;

        if ($handler === null) {
            throw new \RuntimeException('Streaming handler not available for streaming conversation.');
        }

        if ($this->conversation->toolExecutor === null) { // Check if tool executor exists
            throw new \RuntimeException('ToolExecutor not available in conversation.');
        }

        // Register tools (same as before)
        $configuredTools = $this->factory->toolConfigService->getToolConfigurations();

        foreach ($configuredTools as $name => $config) {
            $toolRegistry->registerTool(
                $name,
                $config['callback'],
                $config['parameters'] ?? [],
                $config['description'] ?? null
            );
        }

        // Add initial messages
        $this->conversation->addSystemMessage('You are a helpful assistant that can use tools like calculating or getting the time.');
        $this->conversation->addUserMessage('What is the time? And then, calculate 100 / (5 + 5).');

        $this->info('Initial prompt sent. Waiting for streaming response and potential tool calls...');

        // --- User-defined listeners (Optional: For observing the process) ---
        $handler->on('stream_content', function (string $content): void {
            // Show initial streamed content before any tool calls
            $this->output->write($content);
        });

        $handler->on('stream_tool_call', function (ToolCall $toolCall, int $index): void {
            $this->newLine(); // Ensure tool call info starts on a new line
            $this->info("  [Stream attempting tool call #{$index}: {$toolCall->name}]");
        });

        $handler->on('stream_end', function ($finalToolCalls): void {
            if (! empty($finalToolCalls)) {
                $this->info('  [Stream ended with final tool calls ready for execution]');
            } else {
                $this->info('  [Stream ended without tool calls]');
            }
            $this->newLine(); // New line after stream finishes
        });

        $this->conversation->eventHandler->on('tool_executed', function (string $toolCallId, string $toolName, $result): void {
            $this->info("  [Executed Tool: {$toolName} (ID: {$toolCallId}) -> Result: ".(is_string($result) ? $result : json_encode($result)).']');
        });

        $handler->on('stream_error', function (Throwable $e): void {
            $this->error("\nStream error reported: {$e->getMessage()}");
        });

        // --- Use the new simplified method ---
        try {
            // handleStreamingTurn orchestrates the stream, tool execution, and final response
            $finalResponseContent = $this->conversation->handleStreamingTurn();

            // Output the final response received *after* any tool execution
            $this->info("\nFinal Assistant Response:");
            $this->line($finalResponseContent);

            return ['details' => 'Streaming with tool calls completed successfully.'];
        } catch (Throwable $e) {
            $this->error("\nError during handleStreamingTurn with tools: {$e->getMessage()}");

            // Re-throw or handle as appropriate for the command's workflow
            throw $e;
        }
    }

    // @TODO: Add more steps for other endpoints (Embeddings, Completions, Structured Output)

    /**
     * Execute a tool by name.
     *
     * @param  string  $name  The name of the tool
     * @param  array<string, mixed>  $arguments  The arguments to pass to the tool
     * @return mixed The result of the tool execution
     *
     * @throws \RuntimeException If the tool is not found
     */
    private function executeTool(string $name, array $arguments)
    {
        // Implementation of executeTool method
    }
}
