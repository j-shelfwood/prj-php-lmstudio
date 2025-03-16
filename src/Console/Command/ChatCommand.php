<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Console\Command;

use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;
use Shelfwood\LMStudio\Core\Builder\ConversationBuilder;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\LMStudioFactory;
use Symfony\Component\Console\Input\InputOption;

class ChatCommand extends BaseCommand
{
    private LMStudioFactory $factory;

    private ?StreamingHandler $streamingHandler = null;

    private ?ToolRegistry $toolRegistry = null;

    private ?EventHandler $eventHandler = null;

    private ?ConversationBuilder $builder = null;

    private $conversation;

    public function __construct(LMStudioFactory $factory)
    {
        parent::__construct('chat');

        $this->factory = $factory;
        $this->toolRegistry = new ToolRegistry;

        $this->setDescription('Interactive chat with LM Studio API including tool calling support')
            ->addOption(
                'model',
                null,
                InputOption::VALUE_OPTIONAL,
                'The model to use (defaults to config value)'
            )
            ->addOption(
                'system',
                null,
                InputOption::VALUE_OPTIONAL,
                'Optional system message to set the behavior'
            );
    }

    protected function handle(): int
    {
        $this->initializeHandlers();

        // Create the builder first
        $this->builder = $this->createConversationBuilder();

        // Register tools after builder is created
        $this->registerTools();

        // Build the conversation
        $this->conversation = $this->builder->build();

        // Add system message if provided
        if ($systemMessage = $this->option('system')) {
            $this->conversation->addSystemMessage($systemMessage);
        }

        $this->info(sprintf('Starting chat with model: %s', $this->getModel()));
        $this->info("Type 'exit' or 'quit' to end the conversation.");
        $this->newLine();

        // Start the chat loop
        $this->chatLoop();

        return self::SUCCESS;
    }

    private function initializeHandlers(): void
    {
        $this->streamingHandler = new StreamingHandler;
        $this->eventHandler = new EventHandler;

        $this->configureStreamingHandlers();
        $this->setupEventHandlers();
    }

    private function configureStreamingHandlers(): void
    {
        $this->streamingHandler
            ->onStart(function (): void {
                try {
                    // Clear the line and prepare for output
                    $this->output->write("\r\033[K");
                } catch (\Throwable $e) {
                    error_log(sprintf('[ERROR] Error in onStart: %s', $e->getMessage()));
                }
            })
            ->onContent(function (string $content): void {
                try {
                    // Write content directly without buffering
                    $this->output->write($content);
                } catch (\Throwable $e) {
                    error_log(sprintf('[ERROR] Error writing content: %s', $e->getMessage()));
                }
            })
            ->onToolCall(function (array|ToolCall $toolCall): void {
                try {
                    // Convert array to ToolCall object if needed
                    $toolCallObj = null;

                    if ($toolCall instanceof ToolCall) {
                        $toolCallObj = $toolCall;
                    } else {
                        try {
                            $toolCallObj = ToolCall::fromArray($toolCall);
                        } catch (\InvalidArgumentException $e) {
                            // Log but don't display partial tool calls during streaming
                            error_log(sprintf('[DEBUG] Invalid tool call format: %s. Data: %s',
                                $e->getMessage(),
                                json_encode($toolCall)
                            ));

                            return;
                        }
                    }

                    // Skip if we couldn't create a valid tool call object
                    if (! $toolCallObj) {
                        return;
                    }

                    // Skip if we don't have a complete tool call
                    if (! $toolCallObj->getName() || empty($toolCallObj->getArguments())) {
                        error_log(sprintf('[DEBUG] Incomplete tool call received: %s', json_encode($toolCall)));

                        return;
                    }

                    // Display the tool call
                    $this->newLine();
                    $this->info(sprintf('🔧 Using tool: %s', $toolCallObj->getName()));

                    $args = $toolCallObj->getArguments();

                    if (! empty($args)) {
                        $prettyArgs = json_encode($args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        $this->line(sprintf('   Arguments: %s', $prettyArgs));
                    }

                    // Execute the tool call
                    try {
                        $result = $this->toolRegistry->executeTool(
                            $toolCallObj->getName(),
                            $toolCallObj->getArguments()
                        );

                        if ($result !== null) {
                            $this->newLine();
                            $this->line(sprintf('   Result: %s',
                                is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT)
                            ));
                        }
                    } catch (\Exception $e) {
                        $this->error(sprintf('Error executing tool %s: %s',
                            $toolCallObj->getName(),
                            $e->getMessage()
                        ));
                    }
                } catch (\JsonException $e) {
                    error_log(sprintf('[ERROR] JSON error in tool call: %s', $e->getMessage()));
                } catch (\Throwable $e) {
                    error_log(sprintf('[ERROR] Unexpected error in tool call: %s', $e->getMessage()));
                    $this->error('An unexpected error occurred while processing the tool call');
                }
            })
            ->onError(function (\Throwable $error): void {
                try {
                    $this->newLine();
                    $this->error(sprintf('Error during streaming: %s', $error->getMessage()));

                    if ($error->getPrevious()) {
                        $this->error(sprintf('Caused by: %s', $error->getPrevious()->getMessage()));
                    }
                    $this->newLine();
                } catch (\Throwable $e) {
                    error_log(sprintf('[ERROR] Error handling streaming error: %s', $e->getMessage()));
                }
            })
            ->onEnd(function (): void {
                try {
                    $this->newLine(2);
                } catch (\Throwable $e) {
                    error_log(sprintf('[ERROR] Error in onEnd: %s', $e->getMessage()));
                }
            });
    }

    private function createConversationBuilder(): ConversationBuilder
    {
        $builder = $this->factory->createStreamingConversationBuilder($this->getModel());

        // Configure streaming
        $builder->withStreaming(true)
            ->withStreamingHandler($this->streamingHandler);

        // Configure event handlers
        $builder->onToolCall(function ($name, $args, $id): void {
            $this->eventHandler->trigger('tool_call', $name, $args, $id);
        })
            ->onToolExecuted(function ($name, $args, $result, $id): void {
                $this->eventHandler->trigger('tool_executed', $name, $args, $result, $id);
            })
            ->onError(function ($error): void {
                $this->eventHandler->trigger('error', $error);
            });

        return $builder;
    }

    private function getModel(): string
    {
        return $this->option('model')
            ?: getenv('LMSTUDIO_DEFAULT_MODEL')
            ?: 'qwen2.5-7b-instruct';
    }

    protected function registerTools(): void
    {
        if (! $this->builder) {
            throw new \RuntimeException('Builder must be initialized before registering tools');
        }

        // Register a tool to get the current time
        $timeParameters = new ToolParameters;
        $timeParameters->addProperty(
            'dummy',
            new ToolParameter('string', 'Dummy parameter for no-parameter tools')
        );

        $timeDefinition = new ToolDefinition(
            'get_current_time',
            'Get the current server time',
            $timeParameters
        );
        $timeTool = new Tool(ToolType::FUNCTION, $timeDefinition);

        $this->builder->withTool(
            $timeTool->getDefinition()->getName(),
            function () {
                return date('Y-m-d H:i:s');
            },
            $timeTool->getDefinition()->getParameters()->toArray(),
            $timeTool->getDefinition()->getDescription()
        );

        // Register a tool to get weather information (simulated)
        $weatherParameters = new ToolParameters;
        $weatherParameters->addProperty(
            'location',
            new ToolParameter('string', 'The location to get weather for')
        );
        $weatherParameters->addRequired('location');

        $weatherDefinition = new ToolDefinition(
            'get_weather',
            'Get the current weather for a location',
            $weatherParameters
        );
        $weatherTool = new Tool(ToolType::FUNCTION, $weatherDefinition);

        $this->builder->withTool(
            $weatherTool->getDefinition()->getName(),
            function ($args) {
                $location = $args['location'] ?? 'Unknown';
                $this->info("🔍 Looking up weather for: $location");

                // Simulate API call delay
                usleep(100000); // Reduced delay to 100ms

                // Return simulated weather data
                return [
                    'location' => $location,
                    'temperature' => rand(0, 35),
                    'condition' => ['Sunny', 'Cloudy', 'Rainy', 'Snowy'][rand(0, 3)],
                    'humidity' => rand(30, 90).'%',
                ];
            },
            $weatherTool->getDefinition()->getParameters()->toArray(),
            $weatherTool->getDefinition()->getDescription()
        );
    }

    protected function chatLoop(): void
    {
        while (true) {
            try {
                // Get user input
                $userInput = $this->ask('You');

                // Check for exit command
                if (in_array(strtolower($userInput), ['exit', 'quit'], true)) {
                    $this->info('Ending conversation. Goodbye!');

                    break;
                }

                // Add user message to conversation
                $this->conversation->addUserMessage($userInput);

                // Get streaming response with retry logic
                $maxRetries = 3;
                $retryCount = 0;
                $lastError = null;

                while ($retryCount < $maxRetries) {
                    try {
                        $this->conversation->getStreamingResponse();

                        break; // Success, exit retry loop
                    } catch (\Throwable $e) {
                        $lastError = $e;
                        $retryCount++;

                        if ($retryCount < $maxRetries) {
                            $this->line(sprintf('⚠️  Retrying... (attempt %d of %d)', $retryCount + 1, $maxRetries));
                            usleep(500000); // Wait 500ms before retrying

                            continue;
                        }

                        // If we've exhausted all retries, throw the last error
                        throw $e;
                    }
                }
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error('Error: '.$e->getMessage());

                if ($e->getPrevious()) {
                    $this->error('Caused by: '.$e->getPrevious()->getMessage());
                }

                // Add more detailed error information for debugging
                if (str_contains($e->getMessage(), 'cURL Error')) {
                    $this->line('⚠️  This appears to be a connection issue with the LM Studio server.');
                    $this->line('Please ensure:');
                    $this->line('1. The LM Studio server is running');
                    $this->line('2. The server is accessible at the configured URL');
                    $this->line('3. The model is properly loaded');
                }

                $this->newLine();

                continue;
            }
        }
    }

    protected function setupEventHandlers(): void
    {
        // Handle tool calls
        $this->eventHandler->on('tool_call', function ($name, $args, $id): void {
            try {
                // Skip if this is a partial tool call
                if (isset($args['dummy']) && $args['dummy'] === 'none') {
                    return;
                }

                $this->newLine();
                $this->info("🔧 Using tool: $name");

                if (! empty($args)) {
                    $this->line('   Arguments: '.json_encode($args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
            } catch (\Throwable $e) {
                error_log(sprintf('[ERROR] Error in tool_call event: %s', $e->getMessage()));
            }
        });

        // Handle tool execution
        $this->eventHandler->on('tool_executed', function ($name, $args, $result, $id): void {
            try {
                // Skip if this is a partial tool call
                if (isset($args['dummy']) && $args['dummy'] === 'none') {
                    return;
                }

                $this->info('✅ Tool result: '.json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $this->newLine();
            } catch (\Throwable $e) {
                error_log(sprintf('[ERROR] Error in tool_executed event: %s', $e->getMessage()));
            }
        });

        // Handle errors
        $this->eventHandler->on('error', function ($error): void {
            try {
                $this->error('Error: '.$error->getMessage());
            } catch (\Throwable $e) {
                error_log(sprintf('[ERROR] Error in error event: %s', $e->getMessage()));
            }
        });
    }
}
