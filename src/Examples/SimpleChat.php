<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Examples;

use Shelfwood\LMStudio\Builders\StreamBuilder;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Conversations\Conversation;
use Shelfwood\LMStudio\OpenAI;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\Tool;

/**
 * Simple example of using the LMStudio API with the improved API.
 */
class SimpleChat
{
    /**
     * Run a simple chat example.
     */
    public static function run(): void
    {
        // Create a configuration
        $config = new LMStudioConfig(
            baseUrl: 'http://localhost:1234/v1',
            apiKey: 'dummy-key'
        );

        // Create a client
        $client = new OpenAI($config);

        // Example 1: Simple conversation with static factory method
        $conversation = Conversation::withSystemMessage(
            $client,
            'You are a helpful assistant that provides concise answers.'
        );

        // Send a message and get a response
        $response = $conversation->send('What is the capital of France?');
        echo "Response: {$response}\n\n";

        // Example 2: Conversation with tools
        $toolRegistry = new ToolRegistry;

        // Register a calculator tool
        $calculatorTool = Tool::function(
            'calculator',
            'Perform a mathematical calculation',
            [
                'expression' => [
                    'type' => 'string',
                    'description' => 'The mathematical expression to evaluate',
                    'required' => true,
                ],
            ]
        );

        $toolRegistry->register($calculatorTool, function ($args) {
            try {
                $expression = $args['expression'] ?? '';
                $result = eval('return '.$expression.';');

                return (string) $result;
            } catch (\Throwable $e) {
                return 'Error: '.$e->getMessage();
            }
        });

        // Create a conversation with tools
        $conversation = Conversation::withTools(
            $client,
            $toolRegistry,
            'You are a helpful assistant that can perform calculations.'
        );

        // Send a message with streaming
        echo "Streaming response:\n";
        $conversation->sendStreaming(
            'What is 123 * 456?',
            function ($chunk): void {
                if ($chunk->hasContent()) {
                    echo $chunk->getContent();
                    flush();
                }
            }
        );
        echo "\n\n";

        // Example 3: Using the builder pattern
        $conversation = Conversation::builder($client)
            ->withTitle('Advanced Conversation')
            ->withModel('qwen2.5-7b-instruct-1m')
            ->withTemperature(0.8)
            ->withSystemMessage('You are a creative assistant.')
            ->withUserMessage('Tell me a short story about a robot.')
            ->build();

        // Get a response
        $response = $conversation->getResponse();
        echo "Story: {$response}\n\n";

        // Example 4: Using the StreamBuilder
        echo "Using StreamBuilder:\n";
        $streamBuilder = StreamBuilder::create($client)
            ->withModel('qwen2.5-7b-instruct-1m')
            ->withHistory($conversation->getHistory())
            ->withCallbacks(
                // Content callback
                function ($chunk): void {
                    if ($chunk->hasContent()) {
                        echo $chunk->getContent();
                        flush();
                    }
                },
                // Tool call callback
                function ($toolCall) {
                    echo "\n[Tool Call: {$toolCall->function->name}]\n";

                    return 'Tool result';
                },
                // Complete callback
                function ($content, $toolCalls): void {
                    echo "\n[Complete]\n";
                },
                // Error callback
                function ($error): void {
                    echo "\n[Error: {$error->getMessage()}]\n";
                }
            );

        // Execute the stream
        $streamBuilder->execute();
    }
}
