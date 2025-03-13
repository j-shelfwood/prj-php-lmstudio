<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Enum\ToolType;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Conversation\Conversation;

describe('Conversation', function (): void {
    beforeEach(function (): void {
        $this->chatService = Mockery::mock(ChatService::class);
        $this->conversation = new Conversation($this->chatService, 'qwen2.5-7b-instruct-1m');
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
            ->with('qwen2.5-7b-instruct-1m', Mockery::type('array'), [])
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
        // Load the mock responses
        $toolResponse = load_mock('chat/tool-response.json');
        $standardResponse = load_mock('chat/standard-response.json');

        $toolCompletionResponse = ChatCompletionResponse::fromArray($toolResponse);
        $finalCompletionResponse = ChatCompletionResponse::fromArray($standardResponse);

        // Create a tool
        $tool = new Tool(
            ToolType::FUNCTION,
            [
                'name' => 'get_current_weather',
                'description' => 'Get the current weather in a location',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => [
                            'type' => 'string',
                            'description' => 'The location to get weather for',
                        ],
                    ],
                    'required' => ['location'],
                ],
            ]
        );

        // Add a user message
        $this->conversation->addUserMessage('What\'s the weather like in London?');

        // Set up the mock to return the tool response first
        $this->chatService->shouldReceive('createCompletion')
            ->once()
            ->with('qwen2.5-7b-instruct-1m', Mockery::type('array'), [])
            ->andReturn($toolCompletionResponse);

        // Get the first response (which should be a tool call)
        $response = $this->conversation->getResponse();

        // Assert the conversation has the tool call
        $messages = $this->conversation->getMessages();
        expect($messages)->toHaveCount(2); // User message and assistant message with tool call

        // Add a tool response
        $this->conversation->addToolMessage('The weather in London is sunny and 22°C.', '201464470');

        // Set up the mock to return the final response
        $this->chatService->shouldReceive('createCompletion')
            ->once()
            ->with('qwen2.5-7b-instruct-1m', Mockery::type('array'), [])
            ->andReturn($finalCompletionResponse);

        // Get the final response
        $finalResponse = $this->conversation->getResponse();

        // Assert the final response is correct
        $expectedContent = "I'm sorry for any inconvenience, but as an AI, I don't have real-time capabilities to provide current weather updates or forecasts. Please check a reliable weather website or app for the most accurate information on the weather in London.";
        expect($finalResponse)->toBe($expectedContent);

        // Assert the conversation history is maintained
        $messages = $this->conversation->getMessages();
        expect($messages)->toHaveCount(4);
        expect($messages[0]->getRole())->toBe(Role::USER);
        expect($messages[1]->getRole())->toBe(Role::ASSISTANT);
        expect($messages[2]->getRole())->toBe(Role::TOOL);
        expect($messages[2]->getContent())->toBe('The weather in London is sunny and 22°C.');
        expect($messages[2]->getToolCallId())->toBe('201464470');
        expect($messages[3]->getRole())->toBe(Role::ASSISTANT);
        expect($messages[3]->getContent())->toBe($expectedContent);
    });
});
