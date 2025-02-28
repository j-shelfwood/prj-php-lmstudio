# Using Tool Functions

Tool functions allow you to define custom functions that the model can call during a conversation. This is useful for tasks like retrieving information from external APIs, performing calculations, or taking actions based on user input.

## Basic Usage

Here's a simple example of how to use tool functions with the LMStudio PHP library:

```php
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;
use Shelfwood\LMStudio\ValueObjects\Tool;
use Shelfwood\LMStudio\Requests\V0\ChatCompletionRequest;

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

// Create a chat completion request with tools
$request = new ChatCompletionRequest($history, 'gpt-3.5-turbo');
$request = $request
    ->withTools([$weatherTool])
    ->withToolChoice('auto');  // Let the model decide when to use tools

// Get a completion
$response = $lmstudio->lms()->chatCompletion($request);
```

## Handling Tool Calls

When the model decides to use a tool, you need to handle the tool call and provide a response:

```php
// Check if the model used a tool
$choice = $response->choices[0];
if (isset($choice->message->toolCalls) && !empty($choice->message->toolCalls)) {
    $toolCall = $choice->message->toolCalls[0];

    // Get the function name and arguments
    $functionName = $toolCall->function->name;
    $arguments = json_decode($toolCall->function->arguments, true);

    // Handle the tool call based on the function name
    if ($functionName === 'get_weather') {
        $location = $arguments['location'] ?? 'unknown';
        $unit = $arguments['unit'] ?? 'celsius';

        // Call your actual weather API here
        $weatherResponse = "It's 72°F (22°C) and sunny in {$location}.";

        // Add the tool response to the chat history
        $history->addMessage(Message::tool($weatherResponse, $toolCall->id));

        // Create a new request with the updated history
        $request = new ChatCompletionRequest($history, 'gpt-3.5-turbo');

        // Get a new completion that incorporates the tool response
        $response = $lmstudio->lms()->chatCompletion($request);
    }
}
```

## Tool Choice Options

You can control when the model uses tools with the `withToolChoice` method:

- `'auto'`: Let the model decide when to use tools
- `'none'`: Disable tool usage
- Specific function: Force the model to use a specific function

```php
// Let the model decide
$request = $request->withToolChoice('auto');

// Disable tool usage
$request = $request->withToolChoice('none');

// Force the model to use a specific function
$request = $request->withToolChoice([
    'type' => 'function',
    'function' => ['name' => 'get_weather'],
]);
```

## Streaming Tool Calls

You can also use tool functions with streaming responses:

```php
// Enable streaming
$request = $request->withStreaming(true);

// Get a streaming completion
$stream = $lmstudio->lms()->streamChatCompletion($request);

// Process the stream
foreach ($stream as $chunk) {
    // Process each chunk
}

// Or accumulate tool calls from the stream
$toolCalls = $lmstudio->lms()->accumulateChatToolCalls($history, [
    'model' => 'gpt-3.5-turbo',
    'tools' => [$weatherTool],
]);
```

## Multiple Tools

You can provide multiple tools to the model:

```php
$weatherTool = Tool::function(/* ... */);
$calculatorTool = Tool::function(/* ... */);
$searchTool = Tool::function(/* ... */);

$request = $request->withTools([$weatherTool, $calculatorTool, $searchTool]);
```

## Complete Example

For a complete example, see the [tool_functions.php](../examples/tool_functions.php) file in the examples directory.
