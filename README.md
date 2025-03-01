# LMStudio PHP

A PHP package for integrating with LMStudio's local API. This library allows you to interact with LMStudio via both OpenAI‑compatible endpoints (/v1) and the new LM Studio REST API endpoints (/api/v0). It supports chat and text completions, embeddings, tool calls (including streaming responses), and is designed with dependency injection in mind.

- PHP 8.2+
- Compatible with Laravel 10.x and 12.x
- Version 1.2.0

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
  - Configurable model and API selection

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
    'model' => 'granite-3.1-8b-instruct',
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
    ['model' => 'granite-3.1-8b-instruct']
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
use Shelfwood\LMStudio\Requests\V1\ChatCompletionRequest;
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
$request = new ChatCompletionRequest($history, 'granite-3.1-8b-instruct');
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
use Shelfwood\LMStudio\Requests\V1\ChatCompletionRequest;
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
$request = new ChatCompletionRequest($history, 'granite-3.1-8b-instruct');
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
    'model' => 'granite-3.1-8b-instruct',
]);

echo $content;
```

### Tool Functions

Tool functions allow the model to call functions you define:

```php
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\Requests\V1\ChatCompletionRequest;
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
$request = new ChatCompletionRequest($history, 'granite-3.1-8b-instruct');
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
        $request = new ChatCompletionRequest($history, 'granite-3.1-8b-instruct');
        $response = $lmstudio->openai()->chatCompletion($request);

        echo $response->choices[0]->message->content;
    }
}
```

### Custom Configuration

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
    defaultModel: 'granite-3.1-8b-instruct',
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
        'model' => 'granite-3.1-8b-instruct',
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
LMSTUDIO_DEFAULT_MODEL=granite-3.1-8b-instruct
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
    'model' => 'granite-3.1-8b-instruct',
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
            'model' => 'granite-3.1-8b-instruct',
        ]);

        return response()->json([
            'response' => $response->choices[0]->message->content,
        ]);
    }
}
```

## Command Line Interface

The package includes a command-line interface for interacting with LMStudio:

```bash
# Interactive chat with a language model
php bin/lmstudio chat

# Run a sequence of API calls to test all endpoints
php bin/lmstudio sequence
```

### Chat Command Options

```bash
# Use a specific model
php bin/lmstudio chat --model=granite-3.1-8b-instruct

# Set a system message
php bin/lmstudio chat --system="You are a helpful assistant that speaks like a pirate."

# Set temperature
php bin/lmstudio chat --temperature=0.8

# Enable streaming (default)
php bin/lmstudio chat --stream

# Disable streaming
php bin/lmstudio chat --no-stream
```

### Sequence Command Options

```bash
# Use a specific model
php bin/lmstudio sequence --model=granite-3.1-8b-instruct

# Show detailed output
php bin/lmstudio sequence --verbose
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
