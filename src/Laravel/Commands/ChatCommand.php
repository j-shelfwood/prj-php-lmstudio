<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Laravel\Commands;

use Illuminate\Console\Command;
use Shelfwood\LMStudio\Core\Builder\ConversationBuilder;
use Shelfwood\LMStudio\LMStudioFactory;

class ChatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lmstudio:chat
                            {--model= : The model to use (defaults to config value)}
                            {--system= : Optional system message to set the behavior}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactive chat with LM Studio API including tool calling support';

    /**
     * The conversation builder.
     *
     * @var ConversationBuilder
     */
    protected $builder;

    /**
     * The conversation instance.
     */
    protected $conversation;

    /**
     * Execute the console command.
     */
    public function handle(LMStudioFactory $factory): int
    {
        // Get the model from options or config
        $model = $this->option('model') ?: config('lmstudio.default_model', 'qwen2.5-7b-instruct-1m');

        try {
            // Verify model exists and is loaded
            $modelService = $factory->createModelService();
            $models = $modelService->listModels()->getModels();
            $modelExists = false;

            foreach ($models as $availableModel) {
                if ($availableModel->getId() === $model) {
                    $modelExists = true;

                    if (! $availableModel->isLoaded()) {
                        $this->error("Model {$model} is not loaded. Please load it in LM Studio first.");

                        return Command::FAILURE;
                    }

                    break;
                }
            }

            if (! $modelExists) {
                $this->error("Model {$model} not found. Use 'lmstudio:models' to see available models.");

                return Command::FAILURE;
            }

            $this->info("Starting chat with model: $model");
            $this->info("Type 'exit' or 'quit' to end the conversation.");
            $this->newLine();

            // Create a conversation builder
            $this->builder = $factory->createConversationBuilder($model);

            // Register tools
            $this->registerTools();

            // Build the conversation
            $this->conversation = $this->builder->build();

            // Add system message if provided
            if ($systemMessage = $this->option('system')) {
                $this->conversation->addSystemMessage($systemMessage);
            }

            // Start the chat loop
            $this->chatLoop();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to initialize chat: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Register tools for the conversation.
     */
    protected function registerTools(): void
    {
        // Register a tool to get the current time
        $this->builder->withTool(
            'get_current_time',
            function () {
                return date('Y-m-d H:i:s');
            },
            [
                'type' => 'object',
                'properties' => [],
                'required' => [],
            ],
            'Get the current server time'
        );

        // Register a tool to get weather information (simulated)
        $this->builder->withTool(
            'get_weather',
            function ($args) {
                $location = $args['location'] ?? 'Unknown';
                $this->info("🔍 Looking up weather for: $location");

                // Simulate API call delay
                sleep(1);

                // Return simulated weather data
                return [
                    'location' => $location,
                    'temperature' => rand(0, 35),
                    'condition' => ['Sunny', 'Cloudy', 'Rainy', 'Snowy'][rand(0, 3)],
                    'humidity' => rand(30, 90).'%',
                ];
            },
            [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The location to get weather for',
                    ],
                ],
                'required' => ['location'],
            ],
            'Get the current weather for a location'
        );
    }

    /**
     * Run the interactive chat loop.
     */
    protected function chatLoop(): void
    {
        while (true) {
            // Get user input
            $userInput = $this->ask('You');

            // Check for exit command
            if (in_array(strtolower($userInput), ['exit', 'quit'], true)) {
                $this->info('Ending conversation. Goodbye!');

                break;
            }

            // Add user message to conversation
            $this->conversation->addUserMessage($userInput);

            // Show thinking indicator
            $this->output->write('AI is thinking');
            $thinking = $this->output->createProgressBar(3);
            $thinking->start();

            // Set up event handlers
            $this->setupEventHandlers();

            // Get response
            $response = $this->conversation->getResponse();

            // Clear thinking indicator
            $thinking->finish();
            $this->output->write("\r".str_repeat(' ', 20)."\r");

            // Display the response
            $this->newLine();
            $this->info('AI: '.$response);
            $this->newLine();
        }
    }

    /**
     * Set up event handlers for the conversation.
     */
    protected function setupEventHandlers(): void
    {
        // Handle tool calls
        $this->conversation->getEventHandler()->on('tool_call', function ($name, $args, $id): void {
            $this->newLine();
            $this->info("🔧 Using tool: $name");
            $this->line('   Arguments: '.json_encode($args));
        });

        // Handle tool execution
        $this->conversation->getEventHandler()->on('tool_executed', function ($name, $args, $result, $id): void {
            $this->info('✅ Tool result: '.json_encode($result));
            $this->newLine();
        });
    }
}
