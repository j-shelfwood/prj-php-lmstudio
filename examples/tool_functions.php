<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\Requests\V0\ChatCompletionRequest;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;
use Shelfwood\LMStudio\ValueObjects\Tool;

// Create a new LMStudio instance
$lmstudio = new LMStudio;

// Define a tool function for getting the weather
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

// Create a chat history with a user message
$history = new ChatHistory([
    Message::system('You are a helpful assistant that can provide weather information.'),
    Message::user('What\'s the weather like in San Francisco?'),
]);

// Create a chat completion request with tools
$request = new ChatCompletionRequest($history, 'gpt-3.5-turbo');
$request = $request
    ->withTools([$weatherTool])
    ->withToolChoice('auto');  // Let the model decide when to use tools

// Get a completion from the LM Studio API
try {
    $response = $lmstudio->lms()->chatCompletion($request);

    // Check if the model used a tool
    $choice = $response->choices[0];

    if (isset($choice->message->toolCalls) && ! empty($choice->message->toolCalls)) {
        $toolCall = $choice->message->toolCalls[0];
        echo "Tool call: {$toolCall->function->name}\n";
        echo "Arguments: {$toolCall->function->arguments}\n";

        // In a real application, you would call your actual weather API here
        // For this example, we'll just simulate a response
        $args = json_decode($toolCall->function->arguments, true);
        $location = $args['location'] ?? 'unknown';
        $unit = $args['unit'] ?? 'celsius';

        // Simulate getting the weather
        $weatherResponse = "It's 72°F (22°C) and sunny in {$location}.";

        // Add the tool response to the chat history
        $history->addMessage(Message::tool($weatherResponse, $toolCall->id));

        // Create a new request with the updated history
        $request = new ChatCompletionRequest($history, 'gpt-3.5-turbo');

        // Get a new completion that incorporates the tool response
        $response = $lmstudio->lms()->chatCompletion($request);

        // Output the final response
        echo "\nFinal response: {$response->choices[0]->message->content}\n";
    } else {
        // The model didn't use a tool
        echo "Response: {$choice->message->content}\n";
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}

// Example with tool_choice set to a specific function
echo "\n\nExample with tool_choice set to a specific function:\n";

$history = new ChatHistory([
    Message::system('You are a helpful assistant that can provide weather information.'),
    Message::user('Tell me about the weather.'),
]);

$request = new ChatCompletionRequest($history, 'gpt-3.5-turbo');
$request = $request
    ->withTools([$weatherTool])
    ->withToolChoice([
        'type' => 'function',
        'function' => ['name' => 'get_weather'],
    ]);

try {
    $response = $lmstudio->lms()->chatCompletion($request);

    // The model should always use the specified tool
    $choice = $response->choices[0];

    if (isset($choice->message->toolCalls) && ! empty($choice->message->toolCalls)) {
        $toolCall = $choice->message->toolCalls[0];
        echo "Tool call: {$toolCall->function->name}\n";
        echo "Arguments: {$toolCall->function->arguments}\n";
    } else {
        echo "Response: {$choice->message->content}\n";
    }
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}
