<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Console\Command;

use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Core\Conversation\Conversation;
use Shelfwood\LMStudio\LMStudioFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class SequenceCommand extends BaseCommand
{
    private LMStudioFactory $factory;

    private array $stepStats = [];

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

        // Get models
        $this->modelId = $this->option('model') ?: getenv('LMSTUDIO_DEFAULT_MODEL') ?: 'qwen2.5-7b-instruct';
        $this->embeddingModel = $this->option('embedding-model') ?: getenv('LMSTUDIO_DEFAULT_EMBEDDING_MODEL') ?: 'text-embedding-nomic-embed-text-v1.5';

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

        $handler = $this->conversation->getStreamingHandler();

        if (! $handler) {
            throw new \RuntimeException('Streaming handler missing from conversation.');
        }

        $fullContent = '';
        $isComplete = false;
        $errorOccurred = null;

        $this->info('Streaming Response:');

        // --- Register Event Listeners on the Handler ---
        $handler->on('stream_content', function ($content) use (&$fullContent): void {
            $this->output->write($content); // Write content as it arrives
            $fullContent .= $content;
        });

        $handler->on('stream_end', function ($finalToolCalls = null, ?object $lastChunk = null) use (&$isComplete): void {
            $this->output->writeln(''); // Newline after final content
            $this->info('  [Stream Event: Ended]');
            $isComplete = true;
            // TODO: Capture token usage from $lastChunk if available and needed for metrics
        });

        $handler->on('stream_error', function (Throwable $error) use (&$isComplete, &$errorOccurred): void {
            $this->output->writeln('');
            $this->error('  [Stream Error]: '.$error->getMessage());
            $errorOccurred = $error;
            $isComplete = true; // End processing on error
        });

        // --- Initiate the Stream ---
        // This call now returns immediately (void).
        $this->conversation->initiateStreamingResponse();

        // --- Wait for Stream Completion (Simulated for Console) ---
        // In a real async context (e.g., web server), you wouldn't block like this.
        // This loop simulates waiting for the stream events to set $isComplete.
        $startTime = microtime(true);
        $timeout = 30; // seconds timeout

        while (! $isComplete) {
            usleep(50000); // Sleep briefly (50ms) to avoid pegging CPU

            if ((microtime(true) - $startTime) > $timeout) {
                $this->error('\n[Stream Timeout]');
                $errorOccurred = new \RuntimeException('Stream timed out after '.$timeout.' seconds');

                break; // Exit loop on timeout
            }
        }

        if ($errorOccurred) {
            // Rethrow or return error details for executeSequenceStep
            throw $errorOccurred; // Let the main handler catch and report
        }

        return ['details' => 'Streaming chat successful', 'tokens' => 'N/A']; // TODO: Extract tokens
    }

    private function runChatWithTools(): array
    {
        $this->conversation = $this->factory->createConversation($this->modelId);
        $weatherToolName = 'get_city_weather';

        // CORRECTED: Manually define parameters array matching JSON schema
        $weatherToolParams = [
            'type' => 'object',
            'properties' => [
                'city' => [
                    'type' => 'string',
                    'description' => 'The city name',
                ],
                'unit' => [
                    'type' => 'string',
                    'description' => 'Temperature unit (celsius or fahrenheit)',
                    'enum' => ['celsius', 'fahrenheit'],
                ],
            ],
            'required' => ['city'],
        ];

        $weatherToolCallback = function (array $args) use ($weatherToolName): string {
            $city = $args['city'] ?? 'N/A';
            $unit = $args['unit'] ?? 'celsius';
            $temp = rand(5, 25).($unit === 'celsius' ? 'C' : 'F');
            $this->info("  [Tool Executing: {$weatherToolName}(city={$city}, unit={$unit})] -> Returning: {$temp}");

            return json_encode(['temperature' => $temp]);
        };

        $this->conversation->getToolRegistry()->registerTool(
            $weatherToolName,
            $weatherToolCallback,
            $weatherToolParams, // Use defined array
            'Get the weather for a city'
        );

        $this->conversation->addSystemMessage('You MUST use tools. Get the weather for London.');
        $this->conversation->addUserMessage('How is the weather in London? Use the tool.');
        $this->info('Sending request, expecting tool use...');
        $finalResponse = $this->conversation->getResponse();
        $this->info("Final Response: {$finalResponse}");

        return ['details' => 'Chat with tools successful', 'tokens' => 'N/A'];
    }

    /**
     * REFACTORED: Run chat completion with tools using streaming.
     */
    private function runChatWithToolsStreaming(): array
    {
        $this->conversation = $this->factory->createStreamingConversation($this->modelId);
        $weatherToolName = 'get_city_weather_streaming'; // CORRECTED: Ensure defined

        // CORRECTED: Manually define parameters array matching JSON schema
        $weatherToolParams = [
            'type' => 'object',
            'properties' => [
                'city' => [
                    'type' => 'string',
                    'description' => 'The city name',
                ],
                'unit' => [
                    'type' => 'string',
                    'description' => 'Temperature unit (celsius or fahrenheit)',
                    'enum' => ['celsius', 'fahrenheit'],
                ],
            ],
            'required' => ['city'],
        ];

        $weatherToolCallback = function (array $args) use ($weatherToolName): string {
            $city = $args['city'] ?? 'N/A';
            $unit = $args['unit'] ?? 'celsius';
            $temp = rand(5, 25).($unit === 'celsius' ? 'C' : 'F');
            $this->info("  [Tool Executing: {$weatherToolName}(city={$city}, unit={$unit})] -> Returning: {$temp}");

            return json_encode(['temperature' => $temp]);
        };

        $this->conversation->getToolRegistry()->registerTool(
            $weatherToolName,
            $weatherToolCallback,
            $weatherToolParams, // Use defined array
            'Get the weather for a city (streaming context)'
        );

        $this->conversation->addSystemMessage('You MUST use tools. Get the weather for Paris.');
        $this->conversation->addUserMessage('Whats the weather like in Paris right now? Please use the tool.');

        // --- Setup Streaming Handler and State ---
        $handler = $this->conversation->getStreamingHandler();

        if (! $handler) {
            throw new \RuntimeException('Streaming handler missing from conversation.');
        }

        $initialStreamComplete = false;
        $finalResponseReceived = false;
        $streamError = null;
        $finalToolCalls = null; // To store tool calls from stream_end
        $finalResponseContent = ''; // To store final textual response

        $this->info('Initiating streaming request with tools...');

        // --- Register Event Listeners ---
        $handler->on('stream_content', function ($content) use (&$finalResponseContent): void {
            $this->output->write($content); // Keep writing to console
            $finalResponseContent .= $content; // Accumulate content
        });

        $handler->on('stream_tool_call_start', fn ($idx) => $this->info("  [Tool Call Start: {$idx}]"));
        $handler->on('stream_tool_call_end', fn ($idx, ToolCall $call) => $this->info("  [Tool Call Assembled: {$idx} - {$call->name}]"));

        $handler->on('stream_end', function ($tools) use (&$initialStreamComplete, &$finalToolCalls): void {
            $this->output->writeln('');
            $this->info('  [Stream Event: Initial Stream Ended]');

            if (! empty($tools) && is_array($tools)) {
                $this->info('  Tool calls received: '.count($tools));
                $finalToolCalls = $tools; // Store the tool calls
            } else {
                $this->info('  No tool calls received in stream.');
            }
            $initialStreamComplete = true;
        });

        $handler->on('stream_error', function (Throwable $error) use (&$initialStreamComplete, &$streamError): void {
            $this->output->writeln('');
            $this->error('  [Stream Error]: '.$error->getMessage());
            $streamError = $error;
            $initialStreamComplete = true; // End processing
        });

        // --- Initiate the First Stream ---
        $this->conversation->initiateStreamingResponse();

        // --- Wait for Initial Stream to Complete (Simulated) ---
        $startTime = microtime(true);
        $timeout = 30; // seconds

        while (! $initialStreamComplete) {
            usleep(50000);

            if ((microtime(true) - $startTime) > $timeout) {
                $streamError = new \RuntimeException('Initial stream timed out after '.$timeout.' seconds');

                break;
            }
        }

        // Handle potential errors from the initial stream
        if ($streamError) {
            throw $streamError;
        }

        // --- Post-Stream Processing ---
        if (! empty($finalToolCalls)) {
            $this->info("\n--- Executing Tools ---");

            try {
                // Execute tools and add results to conversation history
                $toolResults = $this->conversation->executeToolCalls($finalToolCalls);
                $this->info('Tool execution finished.');
                // Optionally display $toolResults

                $this->info("\n--- Initiating Final Response Stream ---");

                // Reset state for the second stream
                $initialStreamComplete = false; // Re-use this flag for the second stream
                $streamError = null;           // Reset error state
                $finalResponseContent = '';   // Clear accumulated content

                // Re-register/ensure 'stream_content' accumulates to $finalResponseContent
                // The existing handler setup should work if $finalResponseContent is captured by reference/scope

                // Initiate the SECOND stream to get the final textual response
                $this->conversation->initiateStreamingResponse();

                // --- Wait for SECOND Stream to Complete (Simulated) ---
                $startTime = microtime(true);
                $timeout = 30; // seconds

                while (! $initialStreamComplete) {
                    usleep(50000);

                    if ((microtime(true) - $startTime) > $timeout) {
                        $streamError = new \RuntimeException('Final response stream timed out after '.$timeout.' seconds');

                        break;
                    }
                }

                // Handle potential errors from the final stream
                if ($streamError) {
                    throw $streamError;
                }

                $this->info('Final Model Response (from stream): '.$finalResponseContent);
                $finalResponseReceived = true;

            } catch (Throwable $e) {
                $this->error("\nError during tool execution or final response fetch: ".$e->getMessage());

                // Decide how to handle - maybe let executeSequenceStep catch it
                throw $e;
            }
        } else {
            $this->info("\nNo tool calls detected. Assuming stream content is the final response.");
            // If no tools were called, the content from the initial stream is the final answer.
            // Note: $fullContent wasn't captured in this refactored version, need to rely on Conversation history or re-add listener
            $lastMessage = end($this->conversation->getMessages());

            if ($lastMessage && $lastMessage->role === Role::ASSISTANT) {
                $finalResponseContent = $lastMessage->content ?? '';
                $this->info('Final Response (from stream): '.$finalResponseContent);
            }
            $finalResponseReceived = true; // Mark as complete even without tools
        }

        // Could add another wait loop here if the getResponse() call was async, but it's synchronous

        return ['details' => 'Streaming with tools successful', 'tokens' => 'N/A']; // TODO: Extract tokens
    }

    // Add placeholders for other tests if uncommented
    // private function runStructuredOutputTest(): array { $this->info("Test not implemented."); return []; }
    // private function runCompletionTest(): array { $this->info("Test not implemented."); return []; }
    // private function runEmbeddingTest(): array { $this->info("Test not implemented."); return []; }

}
