# LMStudio PHP

A PHP package for integrating with LMStudio's local API. This library allows you to interact with LMStudio via both OpenAI‑compatible endpoints (/v1) and the new LM Studio REST API endpoints (/api/v0). It supports chat and text completions, embeddings, tool calls (including streaming responses), and is designed with dependency injection in mind.

## Features

- **OpenAI Compatibility Endpoints (/v1):**

  - List models, get model information, chat completions, text completions, and embeddings.
  - Support for streaming responses and tool call handling.

- **LM Studio REST API Endpoints (/api/v0):**

  - List all available models and get detailed model info.
  - Create chat completions, text completions, and embeddings using the REST API.

- **Tool Calls & Structured Output:**

  - Parse and execute tool calls requested by LMStudio.
  - Accumulate partial streaming chunks for tool calls.

- **Dependency Injection & Extensibility:**

  - Designed to work seamlessly with DI containers (e.g., Laravel's service container).
  - Interfaces for the API client and streaming response handler for easier customization.

- **Laravel Integration:**
  - Provides a Laravel service provider and facade for effortless integration.
  - Artisan commands available for interactive usage (e.g., Chat, Models, Tools, ToolResponse).

## Installation

Use Composer to install the package:

```bash
composer require shelfwood/lmstudio-php
```

If you're using Laravel, the package will auto-discover the service provider and alias. Otherwise, you can manually register the service provider:

```php
// config/app.php (for Laravel)
'providers' => [
    // Other Service Providers
    Shelfwood\LMStudio\Providers\LMStudioServiceProvider::class,
],
'aliases' => [
    // Other Facades
    'LMStudio' => Shelfwood\LMStudio\Facades\LMStudio::class,
],
```

## Configuration

Publish the configuration file (if using Laravel):

```bash
php artisan vendor:publish --tag=lmstudio-config
```

This will create a `lmstudio.php` configuration file in your `config` directory. You can customize settings such as:

- `host`: LMStudio server hostname (default: `localhost`)
- `port`: LMStudio server port (default: `1234`)
- `timeout`: Connection timeout (default: `60`)
- `retry_attempts` and `retry_delay`: For handling transient connection errors
- `default_model`: The default model identifier
- `temperature` and `max_tokens`: Inference parameters

You can also set these values using environment variables:

```dotenv
LMSTUDIO_HOST=localhost
LMSTUDIO_PORT=1234
LMSTUDIO_TIMEOUT=60
LMSTUDIO_DEFAULT_MODEL=your-model
LMSTUDIO_TEMPERATURE=0.7
LMSTUDIO_MAX_TOKENS=-1
```

## Usage

### Instantiating LMStudio

You can create an instance directly or via dependency injection. For example:

```php
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\DTOs\Common\Config;

// Direct instantiation using default settings:
$lmstudio = LMStudio::create();

// Or with custom configuration:
$config = new Config(
    host: 'localhost',
    port: 1234,
    timeout: 60,
    temperature: 0.7,
    maxTokens: -1,
    defaultModel: 'your-model'
);
$lmstudio = new LMStudio($config);
```

If you use a DI container (like in Laravel), the `LMStudioServiceProvider` will inject the proper dependencies.

### OpenAI-Compatible API (/v1)

#### Listing Models

```php
use Shelfwood\LMStudio\LMStudio;

$lmstudio = LMStudio::create();
$modelList = $lmstudio->listModels();

foreach ($modelList->data as $model) {
    echo $model->id, PHP_EOL;
}
```

#### Chat Completion

```php
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Chat\Role;

$messages = [
    new Message(Role::SYSTEM, 'You are a helpful assistant.'),
    new Message(Role::USER, 'What is the weather like in London?'),
];

$response = $lmstudio->createChatCompletion($messages, 'your-model');

// The response is now a ChatCompletion DTO with strongly typed properties
echo $response->choices[0]->message->content;

// You can also access additional metadata
echo "Model: {$response->model}\n";
echo "Token usage: {$response->usage->totalTokens}\n";
```

#### Text Completion

```php
$response = $lmstudio->createTextCompletion(
    prompt: 'Once upon a time, ',
    model: 'your-model'
);

// Response is now a TextCompletion DTO
echo $response->choices[0]->text;

// Access metadata
echo "Model: {$response->model}\n";
echo "Usage: {$response->usage->totalTokens} tokens\n";
```

#### Embeddings

```php
$embeddings = $lmstudio->createEmbeddings('your-model', 'Some text to embed');

// Response is now an Embedding DTO
$vector = $embeddings->data[0]->embedding;
echo "Vector dimension: " . count($vector) . "\n";
echo "Model: {$embeddings->model}\n";
echo "Usage: {$embeddings->usage->totalTokens} tokens\n";
```

### LM Studio REST API (/api/v0)

These endpoints work similarly but target the new REST API.

#### Listing Models via REST API

```php
$restModels = $lmstudio->listRestModels();
print_r($restModels);
```

#### Getting Model Information

```php
$modelInfo = $lmstudio->getRestModel('your-model');
print_r($modelInfo);
```

#### REST Chat Completion

```php
$response = $lmstudio->createRestChatCompletion($messages, 'your-model');
echo $response['choices'][0]['message']['content'];
```

#### REST Text Completion

```php
$response = $lmstudio->createRestCompletion('Tell me a joke.', 'your-model');
echo $response['choices'][0]['text'];
```

#### REST Embeddings

```php
$embeddings = $lmstudio->createRestEmbeddings('your-model', 'Some text to embed');
print_r($embeddings);
```

### Tool Calls & Streaming

Your library supports tool calls. When using the streaming mode, tool calls can be parsed and executed via your registered handlers. For example:

```php
use Shelfwood\LMStudio\DTOs\Tool\ToolFunction;
use Shelfwood\LMStudio\DTOs\Tool\ToolCall;
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Chat\Role;

// Define a tool function for getting current weather.
$weatherTool = new ToolFunction(
    name: 'get_current_weather',
    description: 'Get the current weather in a location',
    parameters: [
        'location' => [
            'type' => 'string',
            'description' => 'The location for which to get weather',
        ],
    ],
    required: ['location']
);

// Register the tool with a handler.
$chat = $lmstudio->chat()
    ->withModel('your-model')
    ->withTools([$weatherTool])
    ->withToolHandler('get_current_weather', function (array $args) {
        // Implement your actual weather lookup here.
        return ['temperature' => 20, 'condition' => 'sunny'];
    });

// Send a chat message that triggers a tool call.
$chat->withMessages([
    new Message(Role::USER, 'What is the weather in London?'),
]);

// Use streaming mode to process responses.
foreach ($chat->stream()->send() as $message) {
    // Process each streamed message or tool call.
    if ($message instanceof Message) {
        echo $message->content;
    } elseif ($message instanceof ToolCall) {
        // Tool calls are now strongly typed DTOs
        $name = $message->function->name;
        $args = $message->function->arguments;

        // The tool call has been processed automatically
        // You can access the result directly
        $result = $message->function->result;
        echo "Tool '{$name}' returned: " . json_encode($result) . "\n";
    }
}
```

## CLI Commands

The package includes several artisan commands (if using Laravel):

- **chat:** Start an interactive chat session.

  ```bash
  # Start a chat with the default model
  php artisan chat

  # Use a specific model
  php artisan chat --model your-model

  # Set a system message
  php artisan chat --model your-model --system "You are a helpful assistant"
  ```

- **models:** List available models.

  ```bash
  php artisan models
  ```

- **tools:** Test tool calls with LMStudio models.

  ```bash
  # Start tool testing with the default model
  php artisan tools

  # Use a specific model
  php artisan tools --model your-model
  ```

- **tool:response:** Process a tool call result.
  ```bash
  # Get a response for a tool call result
  php artisan tool:response --model your-model get_current_weather '{"temperature":20,"condition":"sunny","location":"London"}'
  ```

You can run these commands directly if you're using the package as a standalone library:

```bash
./vendor/bin/lmstudio chat
./vendor/bin/lmstudio models
./vendor/bin/lmstudio tools
./vendor/bin/lmstudio tool:response --model your-model <tool> <result>
```

## Dependency Injection

The library now supports dependency injection. You can instantiate `LMStudio` by passing in your own implementations of `ApiClientInterface` and `StreamingResponseHandlerInterface`. If not provided, default implementations are used.

For Laravel users, the `LMStudioServiceProvider` handles instantiation and binding.

## Contributing

Contributions are welcome!

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
