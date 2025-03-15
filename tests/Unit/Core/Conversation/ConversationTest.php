<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Enum\FinishReason;
use Shelfwood\LMStudio\Api\Enum\ResponseFormatType;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Model\Choice;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\ResponseFormat;
use Shelfwood\LMStudio\Api\Model\Tool\ToolDefinition;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameter;
use Shelfwood\LMStudio\Api\Model\Tool\ToolParameters;
use Shelfwood\LMStudio\Api\Model\Usage;
use Shelfwood\LMStudio\Api\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Conversation\Conversation;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;

describe('Conversation', function (): void {
    beforeEach(function (): void {
        $this->chatService = Mockery::mock(ChatService::class);
        $this->toolRegistry = Mockery::mock(ToolRegistry::class);
        $this->eventHandler = new EventHandler;

        // Set up default expectations for toolRegistry
        $this->toolRegistry->shouldReceive('hasTools')
            ->andReturn(false);
        $this->toolRegistry->shouldReceive('getToolsArray')
            ->andReturn([]);

        $this->conversation = new Conversation(
            $this->chatService,
            'qwen2.5-7b-instruct-1m',
            [],
            $this->toolRegistry,
            $this->eventHandler
        );
    });

    test('conversation can get response and maintain history', function (): void {
        // Load the mock response
        $mockResponse = load_mock('chat/standard-response.json');
        $chatCompletionResponse = ChatCompletionResponse::fromArray($mockResponse);

        // Add a system message
        $this->conversation->addSystemMessage('You are a helpful assistant.');

        // Add a user message
        $this->conversation->addUserMessage('What\'s the weather like in London?');

        // Set up the mock to return the mock response
        $this->chatService->shouldReceive('createCompletion')
            ->once()
            ->with('qwen2.5-7b-instruct-1m', Mockery::type('array'), null)
            ->andReturn($chatCompletionResponse);

        // Get a response
        $response = $this->conversation->getResponse();

        // Assert the response is correct
        $expectedContent = "I'm sorry for any inconvenience, but as an AI, I don't have real-time capabilities to provide current weather updates or forecasts. Please check a reliable weather website or app for the most accurate information on the weather in London.";
        expect($response)->toBe($expectedContent);

        // Assert the conversation history is maintained
        $messages = $this->conversation->getMessages();
        expect($messages)->toHaveCount(3);
        expect($messages[0]->getRole())->toBe(Role::SYSTEM);
        expect($messages[0]->getContent())->toBe('You are a helpful assistant.');
        expect($messages[1]->getRole())->toBe(Role::USER);
        expect($messages[1]->getContent())->toBe('What\'s the weather like in London?');
        expect($messages[2]->getRole())->toBe(Role::ASSISTANT);
        expect($messages[2]->getContent())->toBe($expectedContent);
    });

    test('conversation can handle tool calls and responses', function (): void {
        // Create test messages
        $messages = [
            Message::forUser('What\'s the weather like in London?'),
        ];

        // Create tool parameters
        $parameters = new ToolParameters;
        $parameters->addProperty('location', new ToolParameter('string', 'The location to get weather for'));

        // Create tool definition
        $definition = new ToolDefinition(
            'get_weather',
            'Get the weather for a location',
            $parameters
        );

        // Set up tool registry expectations
        $this->toolRegistry->shouldReceive('hasTools')
            ->andReturn(true);
        $this->toolRegistry->shouldReceive('getToolsArray')
            ->andReturn([
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_weather',
                        'description' => 'Get the weather for a location',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'location' => [
                                    'type' => 'string',
                                    'description' => 'The location to get weather for',
                                ],
                            ],
                            'required' => [],
                        ],
                    ],
                ],
            ]);
        $this->toolRegistry->shouldReceive('getTool')
            ->with('get_weather')
            ->andReturn($definition);
        $this->toolRegistry->shouldReceive('hasTool')
            ->with('get_weather')
            ->andReturn(true);
        $this->toolRegistry->shouldReceive('executeTool')
            ->with('get_weather', ['location' => 'London'])
            ->andReturn(['temperature' => 20, 'conditions' => 'sunny']);

        // Mock the API response
        $apiResponse = new ChatCompletionResponse(
            'chatcmpl-123',
            'chat.completion',
            1677858242,
            'qwen2.5-7b-instruct-1m',
            [
                new Choice(
                    0,
                    null,
                    FinishReason::TOOL_CALLS,
                    [
                        'role' => 'assistant',
                        'content' => 'I will check the weather in London.',
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"location":"London"}',
                                ],
                            ],
                        ],
                    ]
                ),
            ],
            new Usage(10, 10, 20)
        );

        // Set up the mock to expect a createCompletion call with the correct data
        $this->chatService->shouldReceive('createCompletion')
            ->once()
            ->with('qwen2.5-7b-instruct-1m', Mockery::type('array'), Mockery::type('array'))
            ->andReturn($apiResponse);

        // Add the message and get the response
        $this->conversation->addUserMessage('What\'s the weather like in London?');
        $response = $this->conversation->getResponse();

        // Assert the response is correct
        expect($response)->toBe('I will check the weather in London.');
    });

    test('conversation can handle structured output', function (): void {
        // Load the mock response
        $mockResponse = load_mock('chat/structured-output-response.json');
        $chatCompletionResponse = ChatCompletionResponse::fromArray($mockResponse);

        // Create a response format
        $jsonSchema = [
            'name' => 'joke_response',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'joke' => [
                        'type' => 'string',
                    ],
                ],
                'required' => ['joke'],
            ],
        ];

        $responseFormat = new ResponseFormat(ResponseFormatType::JSON_SCHEMA, $jsonSchema);

        // Add a user message
        $this->conversation->addUserMessage('Tell me a joke.');

        // Set the response format
        $this->conversation->setOptions(['response_format' => $responseFormat]);

        // Set up the mock to return the mock response
        $this->chatService->shouldReceive('createCompletion')
            ->once()
            ->with('qwen2.5-7b-instruct-1m', Mockery::type('array'), Mockery::type('array'))
            ->andReturn($chatCompletionResponse);

        // Get a response
        $response = $this->conversation->getResponse();

        // Assert the response is correct
        $expectedContent = '{"joke":"Why don\'t scientists trust atoms? Because they make up everything!"}';
        expect($response)->toBe($expectedContent);

        // Assert the conversation history is maintained
        $messages = $this->conversation->getMessages();
        expect($messages)->toHaveCount(2);
        expect($messages[0]->getRole())->toBe(Role::USER);
        expect($messages[0]->getContent())->toBe('Tell me a joke.');
        expect($messages[1]->getRole())->toBe(Role::ASSISTANT);
        expect($messages[1]->getContent())->toBe($expectedContent);
    });
});
