# LMStudio PHP Client

A PHP client for interacting with LMStudio and OpenAI-compatible APIs.

## Installation

```bash
composer require shelfwood/lmstudio
```

## Basic Usage

```php
<?php

require 'vendor/autoload.php';

use Shelfwood\LMStudio\LMStudioFactory;

// Assuming LMSTUDIO_API_BASE is set in your environment or .env
$factory = new LMStudioFactory();

// --- Non-Streaming Chat ---
$conversation = $factory->createConversation('your-model-id');
$conversation->addSystemMessage('You are helpful.');
$conversation->addUserMessage('What is the capital of Spain?');

$response = $conversation->getResponse(); // Handles the API call
echo "Assistant: {$response}\n";

// --- Streaming Chat ---
$streamingConversation = $factory->createStreamingConversation('your-model-id');
$streamingConversation->addSystemMessage('You are helpful.');
$streamingConversation->addUserMessage('Explain black holes simply.');

// Optional: Add listeners to observe the stream
$handler = $streamingConversation->streamingHandler;
if ($handler) {
    $handler->on('stream_content', fn(string $content) => print($content));
    $handler->on('stream_end', fn() => print("\n"));
}

// handleStreamingTurn manages the entire process
$finalResponse = $streamingConversation->handleStreamingTurn();
echo "\nFinal Response (after stream): {$finalResponse}\n";

```

## Usage with Tools

Tools allow the model to call predefined PHP functions.

```php
<?php

require 'vendor/autoload.php';

use Shelfwood\LMStudio\LMStudioFactory;

$factory = new LMStudioFactory();

// --- Non-Streaming Chat with Tools ---
$conversationWithTools = $factory->createConversation('your-model-id');

// Register tools (manually or load from config service)
$toolRegistry = $conversationWithTools->toolRegistry;
$toolRegistry->registerTool(
    'calculate',
    fn(string $expression) => ['result' => eval("return {$expression};")], // Basic eval - CAUTION in production!
    [
        'type' => 'object',
        'properties' => [
            'expression' => ['type' => 'string', 'description' => 'Math expression']
        ],
        'required' => ['expression']
    ],
    'Calculate a math expression.'
);

$conversationWithTools->addUserMessage('What is 5 * 8?');

// getResponse handles the tool call automatically if the model decides to use it
$responseWithTools = $conversationWithTools->getResponse();
echo "Assistant (Tools): {$responseWithTools}\n";

// --- Streaming Chat with Tools ---
$streamingConvWithTools = $factory->createStreamingConversation('your-model-id');

// Register tools (same as above)
$toolRegistryStream = $streamingConvWithTools->toolRegistry;
$toolRegistryStream->registerTool('calculate', /* ... same definition ... */ );

$streamingConvWithTools->addUserMessage('Calculate 100 / 4 please.');

// Optional: Listen for tool events
$handlerTools = $streamingConvWithTools->streamingHandler;
if ($handlerTools) {
    $handlerTools->on('stream_content', fn(string $content) => print($content));
    $handlerTools->on('stream_tool_call', fn($toolCall) => print("\n[Tool Call: {$toolCall->name}]\n"));
    $handlerTools->on('stream_end', fn() => print("\n"));
}
$streamingConvWithTools->eventHandler->on('tool_executed', fn($id, $name, $result) => print("[Tool Executed: {$name} -> ".json_encode($result)."]\n"));

// handleStreamingTurn manages the stream, tool execution, and final response
$finalResponseToolsStream = $streamingConvWithTools->handleStreamingTurn();
echo "\nFinal Response (Tools, Stream): {$finalResponseToolsStream}\n";

```

## Console Commands

The library provides commands for interacting with the API via the console (useful for testing and demonstration).

**Setup (if using Laravel):**

1.  Publish the configuration:
    ```bash
    php artisan vendor:publish --tag="lmstudio-config"
    ```
2.  Set your `LMSTUDIO_API_BASE` and optionally `LMSTUDIO_DEFAULT_MODEL` in your `.env` file.

**Available Commands:**

- **`php <your_console_runner> sequence`**: Runs a predefined sequence of API calls (list models, chat, streaming chat, tools) to demonstrate functionality.
  - `--model=<model_id>`: Specify a model to use.
- **`php <your_console_runner> chat`**: Starts an interactive, non-streaming chat session in your terminal. Supports tool use.
  - `--model=<model_id>`: Specify a model to use.
  - `--system="Your prompt"`: Set a custom system prompt.
  - Type `/quit` to exit the chat.

Replace `<your_console_runner>` with `artisan` for Laravel or your specific entry point (e.g., `bin/lmstudio` if you set up a custom runner).

## License

MIT
