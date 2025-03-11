# LMStudio PHP

A PHP package for integrating with LMStudio's local API. This library allows you to interact with LMStudio via both OpenAI‑compatible endpoints (/v1) and the new LM Studio REST API endpoints (/api/v0). It supports chat and text completions, embeddings, tool calls (including streaming responses), and is designed with dependency injection in mind.

- PHP 8.2+
- Compatible with Laravel 10.x and 12.x
- Version 1.2.1

## Features

- **Fully type hinted objects for building chats/requests and responses**

- **OpenAI Compatibility Endpoints (/v1):**

  - List models, get model information, chat completions, text completions, and embeddings.
  - Support for streaming responses and tool call handling.

- **LM Studio REST API Endpoints (/api/v0):**

  - List all available models and get detailed model info.
  - Create chat completions, text completions, and embeddings using the REST API.

- **Tool Calls & Structured Output:**

  - Parse and execute tool calls requested by LMStudio.
  - Accumulate partial streaming chunks for tool calls.
  - [Learn more about using tool functions](docs/tool-functions.md)

- **Dependency Injection & Extensibility:**

  - Designed to work seamlessly with DI containers (e.g., Laravel's service container).
  - Interfaces for the API client and streaming response handler for easier customization.

- **Laravel Integration:**

  - Provides a Laravel service provider and facade for effortless integration.
  - Artisan commands available for interactive usage (e.g., Chat, Models, Tools, ToolResponse).

- **Command Line Interface:**

  - Interactive chat command for chatting with language models
  - Sequence command for testing all API endpoints
  - Tool test command for automated testing of tool functionality
  - Configurable model and API selection
  - Artisan-style command runner (`php lmstudio <command>`)

- **Robust Streaming Support:**

  - Automatic retries for failed streaming requests
  - Configurable timeouts and connection settings
  - Detailed error diagnostics with `StreamingException`

- **Debugging & Logging:**
  - Structured logging with configurable verbosity
  - File-based logging support
  - PSR-3 logger compatibility

## Installation

You can install the package via composer:

```bash
composer require shelfwood/lmstudio-php
```

## Basic Usage

### Simple Chat Completion

```php
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;

// Create a new LMStudio instance
$lmstudio = new LMStudio();

// Create a chat history
$history = new ChatHistory([
    Message::system('You are a helpful assistant.'),
    Message::user('Hello, how are you?'),
]);

// Get a response using the OpenAI-compatible API
$response = $lmstudio->openai()->chat($history->toArray(), [
    'model' => 'qwen2.5-7b-instruct-1m',
]);

echo $response->choices[0]->message->content;
```

### Text Completion

```php
use Shelfwood\LMStudio\LMStudio;

// Create a new LMStudio instance
$lmstudio = new LMStudio();

// Get a text completion
$response = $lmstudio->openai()->completion(
    'Once upon a time in a land far, far away,',
    ['model' => 'qwen2.5-7b-instruct-1m']
);

echo $response->choices[0]->text;
```

### Embeddings

```php
use Shelfwood\LMStudio\LMStudio;

// Create a new LMStudio instance
$lmstudio = new LMStudio();

// Get embeddings for a text
$response = $lmstudio->openai()->embeddings(
    'The quick brown fox jumps over the lazy dog',
    ['model' => 'text-embedding-ada-002']
);

// Access the embedding vector
$embedding = $response->data[0]->embedding;
```

## Advanced Usage

### Using Request Objects

For more control, you can use request objects:

```php
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\Http\Requests\V1\ChatCompletionRequest;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;

// Create a new LMStudio instance
$lmstudio = new LMStudio();

// Create a chat history
$history = new ChatHistory([
    Message::system('You are a helpful assistant.'),
    Message::user('Write a short poem about programming.'),
]);

// Create a request object
$request = new ChatCompletionRequest($history, 'qwen2.5-7b-instruct-1m');
$request = $request
    ->withTemperature(0.7)
    ->withMaxTokens(500)
    ->withTopP(0.9);

// Get a response
$response = $lmstudio->openai()->chatCompletion($request);

echo $response->choices[0]->message->content;
```

### Streaming Responses

Streaming allows you to receive responses incrementally:

```php
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\Http\Requests\V1\ChatCompletionRequest;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;

// Create a new LMStudio instance
$lmstudio = new LMStudio();

// Create a chat history
$history = new ChatHistory([
    Message::system('You are a helpful assistant.'),
    Message::user('Tell me a story about a robot.'),
]);

// Create a request object with streaming enabled
$request = new ChatCompletionRequest($history, 'qwen2.5-7b-instruct-1m');
$request = $request->withStreaming(true);

// Get a streaming response
$stream = $lmstudio->openai()->streamChatCompletion($request);

// Process the stream
foreach ($stream as $chunk) {
    if (isset($chunk['choices'][0]['delta']['content'])) {
        echo $chunk['choices'][0]['delta']['content'];
        flush(); // Flush output buffer to see results immediately
    }
}
```

### Enhanced Streaming with StreamBuilder

For more control over streaming, you can use the StreamBuilder class:

```php
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\Builders\StreamBuilder;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;

// Create a new LMStudio instance
$lmstudio = new LMStudio();

// Create a chat history
$history = new ChatHistory([
    Message::system('You are a helpful assistant.'),
    Message::user('Tell me a story about a robot.'),
]);

// Create a stream builder
$streamBuilder = new StreamBuilder($lmstudio->lms());
$streamBuilder
    ->withHistory($history)
    ->withModel('qwen2.5-7b-instruct-1m')
    ->withTemperature(0.7)
    ->stream(function ($chunk) {
        // Handle content chunks
        if ($chunk->hasContent()) {
            echo $chunk->getContent();
            flush();
        }
    })
    ->onComplete(function ($content, $toolCalls) {
        // Handle completion
        echo "\nCompleted!";
    })
    ->onError(function ($error) {
        // Handle errors
        echo "Error: " . $error->getMessage();
    })
    ->execute();
```

### Accumulating Streaming Content

You can also accumulate content from a streaming response:

```php
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;

// Create a new LMStudio instance
$lmstudio = new LMStudio();

// Create a chat history
$history = new ChatHistory([
    Message::system('You are a helpful assistant.'),
    Message::user('Explain quantum computing in simple terms.'),
]);

// Accumulate content from a streaming response
$content = $lmstudio->openai()->accumulateChatContent($history, [
    'model' => 'qwen2.5-7b-instruct-1m',
]);

echo $content;
```

### Tool Functions

Tool functions allow the model to call functions you define:

```php
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\Http\Requests\V1\ChatCompletionRequest;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;
use Shelfwood\LMStudio\ValueObjects\Tool;

// Create a new LMStudio instance
$lmstudio = new LMStudio();

// Define a tool function
$weatherTool = Tool::function(
    'get_weather',
    'Get the current weather in a given location',
    [
        'location' => [
            'type' => 'string',
            'description' => 'The city and state, e.g. San Francisco, CA',
            'required' => true,
        ],
        'unit' => [
            'type' => 'string',
            'enum' => ['celsius', 'fahrenheit'],
            'description' => 'The unit of temperature',
            'required' => false,
        ],
    ]
);

// Create a chat history
$history = new ChatHistory([
    Message::system('You are a helpful assistant that can provide weather information.'),
    Message::user('What\'s the weather like in San Francisco?'),
]);

// Create a request with tools
$request = new ChatCompletionRequest($history, 'qwen2.5-7b-instruct-1m');
$request = $request
    ->withTools([$weatherTool])
    ->withToolChoice('auto');  // Let the model decide when to use tools

// Get a response
$response = $lmstudio->openai()->chatCompletion($request);

// Check if the model used a tool
$choice = $response->choices[0];
if (isset($choice->message->toolCalls) && !empty($choice->message->toolCalls)) {
    $toolCall = $choice->message->toolCalls[0];

    // Get the function name and arguments
    $functionName = $toolCall->function->name;
    $arguments = json_decode($toolCall->function->arguments, true);

    // Handle the tool call
    if ($functionName === 'get_weather') {
        $location = $arguments['location'] ?? 'unknown';
        $unit = $arguments['unit'] ?? 'celsius';

        // Call your actual weather API here
        $weatherResponse = "It's 72°F (22°C) and sunny in {$location}.";

        // Add the tool response to the chat history
        $history->addMessage(Message::tool($weatherResponse, $toolCall->id));

        // Get a new completion with the tool response
        $request = new ChatCompletionRequest($history, 'qwen2.5-7b-instruct-1m');
        $response = $lmstudio->openai()->chatCompletion($request);

        echo $response->choices[0]->message->content;
    }
}
```

### Enhanced Tool Support with ToolRegistry

For more advanced tool handling, you can use the ToolRegistry class:

```php
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\Tool;
use Shelfwood\LMStudio\Builders\StreamBuilder;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;

// Create a new LMStudio instance
$lmstudio = new LMStudio();

// Create a tool registry
$toolRegistry = new ToolRegistry();

// Register a calculator tool
$toolRegistry->register(
    Tool::function(
        'calculator',
        'Perform a mathematical calculation',
        [
            'expression' => [
                'type' => 'string',
                'description' => 'The mathematical expression to evaluate',
                'required' => true,
            ],
        ]
    ),
    function ($arguments) {
        $expression = $arguments['expression'] ?? '';
        return "Result: " . eval('return ' . $expression . ';');
    }
);

// Create a chat history
$history = new ChatHistory([
    Message::system('You are a helpful assistant that can perform calculations.'),
    Message::user('What is 125 * 37?'),
]);

// Stream with tool support
$streamBuilder = new StreamBuilder($lmstudio->lms());
$streamBuilder
    ->withHistory($history)
    ->withToolRegistry($toolRegistry)
    ->stream(function ($chunk) {
        if ($chunk->hasContent()) {
            echo $chunk->getContent();
        }
    })
    ->onToolCall(function ($toolCall) {
        echo "\nUsing calculator tool...\n";
        $result = "Result: " . (125 * 37);
        echo "Tool result: " . $result . "\n";
        return $result;
    })
    ->execute();
```

## Conversation Management

The library provides a `Conversation` class for managing chat conversations. This class has been refactored to follow SOLID principles, with separate components for different responsibilities:

### Core Conversation Class

The `Conversation` class manages the basic properties and methods for a conversation:

```php
// Create a new conversation
$conversation = new Conversation($client);

// Add messages
$conversation->addSystemMessage('You are a helpful assistant.');
$conversation->addUserMessage('Hello!');

// Get a response
$response = $conversation->getResponse();

// Stream a response
$conversation->streamResponse(function ($chunk) {
    echo $chunk->getContent();
});

// Save and load conversations
$json = $conversation->toJson();
$loadedConversation = Conversation::fromJson($json, $client);
```

### ConversationBuilder

For a fluent API to create conversations, use the `ConversationBuilder`:

```php
// Create a conversation using the builder
$conversation = Conversation::builder($client)
    ->withTitle('My Conversation')
    ->withModel('gpt-4')
    ->withTemperature(0.7)
    ->withMaxTokens(2000)
    ->withSystemMessage('You are a helpful assistant.')
    ->withUserMessage('Hello!')
    ->withMetadata(['category' => 'general'])
    ->build();
```

### ConversationSerializer

The `ConversationSerializer` handles serialization and deserialization of conversations:

```php
// Serialize a conversation
$serializer = new ConversationSerializer();
$json = $serializer->toJson($conversation);

// Deserialize a conversation
$conversation = $serializer->fromJson($json, $client);
```

### ConversationToolHandler

The `ConversationToolHandler` manages tool-related functionality:

```php
// Create a tool handler
$toolHandler = new ConversationToolHandler();
$toolHandler->setToolRegistry($toolRegistry);

// Check if tools are available
if ($toolHandler->hasTools()) {
    // Get tools for request
    $tools = $toolHandler->getToolsForRequest();
}

// Execute a tool call
$result = $toolHandler->executeToolCall($toolCall);

// Process tool calls from a response
$toolHandler->processToolCalls($response, function ($result, $toolCallId) {
    // Handle tool message
});
```

### ConversationStreamHandler

The `ConversationStreamHandler` manages streaming functionality:

```php
// Create a stream handler
$streamHandler = new ConversationStreamHandler(
    $client,
    $history,
    $model,
    $temperature,
    $maxTokens,
    $toolHandler
);

// Stream a response
$streamHandler->streamResponse(
    function ($chunk) {
        // Handle content chunk
        echo $chunk->getContent();
    },
    function ($result, $toolCallId) {
        // Handle tool message
    }
);
```

## Custom Configuration

You can customize the client configuration:

```php
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\LMStudio;

// Create a custom configuration
$config = new LMStudioConfig(
    baseUrl: 'http://localhost:1234',
    apiKey: 'your-api-key',
    timeout: 60,
    headers: ['X-Custom-Header' => 'Value'],
    defaultModel: 'qwen2.5-7b-instruct-1m',
    connectTimeout: 15,
    idleTimeout: 20,
    maxRetries: 5,
    healthCheckEnabled: true,
    debugConfig: [
        'enabled' => true,
        'verbose' => true,
        'log_file' => '/path/to/log/file.log',
    ]
);

// Create a new LMStudio instance with the custom configuration
$lmstudio = new LMStudio($config);

// Or use the immutable configuration methods
$lmstudio = (new LMStudio())
    ->withBaseUrl('http://localhost:1234')
    ->withApiKey('your-api-key')
    ->withTimeout(60);
```

### Error Handling

```php
use Shelfwood\LMStudio\Exceptions\LMStudioException;
use Shelfwood\LMStudio\Exceptions\StreamingException;
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;

// Create a new LMStudio instance
$lmstudio = new LMStudio();

// Create a chat history
$history = new ChatHistory([
    Message::system('You are a helpful assistant.'),
    Message::user('Hello, how are you?'),
]);

try {
    // Get a response
    $response = $lmstudio->openai()->chat($history->toArray(), [
        'model' => 'qwen2.5-7b-instruct-1m',
    ]);

    echo $response->choices[0]->message->content;
} catch (StreamingException $e) {
    // Handle streaming-specific errors
    echo "Streaming error: " . $e->getMessage();
    echo "Chunks received: " . $e->getChunksReceived();
    echo "Last chunk: " . $e->getLastChunk();
    echo "Elapsed time: " . $e->getElapsedTime() . " seconds";
} catch (LMStudioException $e) {
    // Handle general errors
    echo "Error: " . $e->getMessage();
}
```

## Laravel Integration

### Configuration

After installing the package, publish the configuration file:

```bash
php artisan vendor:publish --tag=lmstudio-config
```

This will create a `config/lmstudio.php` file that you can customize.

### Environment Variables

Add these variables to your `.env` file:

```
LMSTUDIO_BASE_URL=http://localhost:1234
LMSTUDIO_API_KEY=lm-studio
LMSTUDIO_DEFAULT_MODEL=qwen2.5-7b-instruct-1m
LMSTUDIO_TIMEOUT=30
LMSTUDIO_CONNECT_TIMEOUT=10
LMSTUDIO_IDLE_TIMEOUT=15
LMSTUDIO_MAX_RETRIES=3
LMSTUDIO_HEALTH_CHECK_ENABLED=true
LMSTUDIO_DEBUG=false
LMSTUDIO_DEBUG_VERBOSE=false
```

### Using the Facade

```php
use Shelfwood\LMStudio\Facades\LMStudio;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;

// Create a chat history
$history = new ChatHistory([
    Message::system('You are a helpful assistant.'),
    Message::user('Hello, how are you?'),
]);

// Get a response using the facade
$response = LMStudio::openai()->chat($history->toArray(), [
    'model' => 'qwen2.5-7b-instruct-1m',
]);

echo $response->choices[0]->message->content;
```

### Using Dependency Injection

```php
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;

class MyController
{
    public function __invoke(LMStudio $lmstudio)
    {
        // Create a chat history
        $history = new ChatHistory([
            Message::system('You are a helpful assistant.'),
            Message::user('Hello, how are you?'),
        ]);

        // Get a response
        $response = $lmstudio->openai()->chat($history->toArray(), [
            'model' => 'qwen2.5-7b-instruct-1m',
        ]);

        return response()->json([
            'response' => $response->choices[0]->message->content,
        ]);
    }
}
```

## Command Line Interface

LMStudio PHP includes a convenient command-line interface for interacting with language models. The commands are accessible through the artisan-style command runner:

```bash
php lmstudio <command> [options]
```

### Available Commands

- **chat**: Interactive chat with a language model

  ```bash
  php lmstudio chat
  ```

- **sequence**: Run a sequence of API calls to test the LM Studio API

  ```bash
  php lmstudio sequence
  ```

- **tool:test**: Test tool functionality with predefined prompts
  ```bash
  php lmstudio tool:test [--tool=<tool>] [--streaming] [--prompt=<custom_prompt>]
  ```
  Options:
  - `--tool`, `-t`: Specific tool to test (calculator, weather, date, or all) [default: "all"]
  - `--streaming`, `-s`: Use streaming mode for responses
  - `--prompt`, `-p`: Custom prompt to test with tools
  - `--model`, `-m`: Specific model to use for testing

### Tool Testing

The `tool:test` command provides an easy way to test tool functionality with the LM Studio API. It includes several built-in tools:

1. **Calculator Tool**: Performs mathematical calculations

   ```bash
   php lmstudio tool:test --tool=calculator --prompt="What is 125 * 37?"
   ```

2. **Weather Tool**: Simulates weather information for locations

   ```bash
   php lmstudio tool:test --tool=weather --prompt="What's the weather like in Amsterdam?"
   ```

3. **Date Tool**: Provides date and time information, including timezone support

   ```bash
   php lmstudio tool:test --tool=date --prompt="What time is it in Asia/Tokyo?"
   ```

4. **All Tools**: Test multiple tools together
   ```bash
   php lmstudio tool:test --prompt="I need to know if I should bring an umbrella to my meeting in London at 3pm. Also, what is 15% of 85?"
   ```

The command will:

- Display the available tools and their parameters
- Send the prompt to the model
- Extract and execute any tool calls made by the model
- Show the results of the tool calls
- Get a follow-up response from the model incorporating the tool results

This is particularly useful for testing how different models handle tool calls and for developing custom tools.

### Common Options

All commands support these common options:

- `--model`, `-m`: The model to use (uses default from config if not specified)
- `--api`, `-a`: Which API to use (openai or lms) [default: "lms"]

### Examples

Test all tools with a custom prompt:

```bash
php lmstudio tool:test --prompt="Calculate 25% of 80 and tell me the weather in Paris"
```

Test only the calculator tool with streaming responses:

```bash
php lmstudio tool:test --tool=calculator --streaming
```

Run an interactive chat session:

```bash
php lmstudio chat
```

## Debugging

You can enable debugging to help troubleshoot issues:

```php
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\LMStudio;

// Enable debugging
$config = new LMStudioConfig(
    debugConfig: [
        'enabled' => true,
        'verbose' => true,
        'log_file' => '/path/to/log/file.log',
    ]
);

$lmstudio = new LMStudio($config);
```

Or set environment variables:

```
LMSTUDIO_DEBUG=true
LMSTUDIO_DEBUG_VERBOSE=true
LMSTUDIO_DEBUG_LOG=/path/to/log/file.log
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Contributions are welcome!

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
