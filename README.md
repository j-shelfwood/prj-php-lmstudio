# LMStudio PHP Client

A PHP client for interacting with LMStudio and OpenAI-compatible APIs.

## Installation

```bash
composer require shelfwood/lmstudio
```

## Basic Usage

### Creating a Client

```php
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\OpenAI;

// Create a configuration
$config = new LMStudioConfig(
    baseUrl: 'http://localhost:1234/v1',  // For LMStudio local server
    apiKey: 'your-api-key'                // Optional for local LMStudio
);

// Create a client
$client = new OpenAI($config);

// For LMStudio API (not OpenAI compatible)
// $client = new Shelfwood\LMStudio\LMS($config);
```

### Simple Conversation

```php
use Shelfwood\LMStudio\Conversations\Conversation;

// Create a conversation with a system message
$conversation = Conversation::withSystemMessage(
    $client,
    'You are a helpful assistant that provides concise answers.'
);

// Send a message and get a response
$response = $conversation->send('What is the capital of France?');
echo "Response: {$response}\n";
```

### Streaming Responses

```php
// Send a message with streaming
$conversation->sendStreaming(
    'Tell me a short story.',
    function ($chunk) {
        if ($chunk->hasContent()) {
            echo $chunk->getContent();
            flush(); // For real-time output in web applications
        }
    }
);
```

### Using Tools

```php
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\Tool;

// Create a tool registry
$toolRegistry = new ToolRegistry();

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

// Send a message that might use tools
$response = $conversation->send('What is 123 * 456?');
echo "Response: {$response}\n";
```

### Using the Builder Pattern

```php
// Create a conversation with the builder pattern
$conversation = Conversation::builder($client)
    ->withTitle('Advanced Conversation')
    ->withModel('qwen2.5-7b-instruct-1m')
    ->withTemperature(0.8)
    ->withSystemMessage('You are a creative assistant.')
    ->withUserMessage('Tell me a short story about a robot.')
    ->build();

// Get a response
$response = $conversation->getResponse();
echo "Story: {$response}\n";
```

### Advanced Streaming with StreamBuilder

```php
use Shelfwood\LMStudio\Builders\StreamBuilder;

// Create a stream builder
$streamBuilder = StreamBuilder::create($client)
    ->withModel('qwen2.5-7b-instruct-1m')
    ->withHistory($conversation->getHistory())
    ->withCallbacks(
        // Content callback
        function ($chunk) {
            if ($chunk->hasContent()) {
                echo $chunk->getContent();
                flush();
            }
        },
        // Tool call callback
        function ($toolCall) {
            echo "\n[Tool Call: {$toolCall->function->name}]\n";
            return "Tool result";
        },
        // Complete callback
        function ($content, $toolCalls) {
            echo "\n[Complete]\n";
        },
        // Error callback
        function ($error) {
            echo "\n[Error: {$error->getMessage()}]\n";
        }
    );

// Execute the stream
$streamBuilder->execute();
```

## Configuration Options

You can configure the client with various options:

```php
$config = new LMStudioConfig(
    baseUrl: 'http://localhost:1234/v1',
    apiKey: 'your-api-key',
    timeout: 30,                  // Request timeout in seconds
    connectTimeout: 5,            // Connection timeout in seconds
    idleTimeout: 120,             // Idle timeout for streaming in seconds
    maxRetries: 3,                // Maximum number of retries for failed requests
    debug: true,                  // Enable debug logging
    defaultModel: 'qwen2.5-7b-instruct-1m' // Default model to use
);
```

## Chat Configuration

You can create and use chat configurations:

```php
use Shelfwood\LMStudio\ValueObjects\ChatConfiguration;

// Create a configuration with the builder pattern
$chatConfig = ChatConfiguration::builder('qwen2.5-7b-instruct-1m')
    ->withTemperature(0.7)
    ->withMaxTokens(2000)
    ->withStreaming(true)
    ->withSystemMessage('You are a helpful assistant.')
    ->build();

// Or create directly
$chatConfig = new ChatConfiguration(
    model: 'qwen2.5-7b-instruct-1m',
    temperature: 0.7,
    maxTokens: 2000,
    streaming: true,
    tools: false,
    systemMessage: 'You are a helpful assistant.',
    metadata: ['user_id' => 123]
);
```

## License

MIT
