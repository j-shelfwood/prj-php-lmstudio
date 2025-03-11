<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Conversations\Conversation;
use Shelfwood\LMStudio\Enums\Role;
use Shelfwood\LMStudio\Enums\ToolType;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\FunctionCall;
use Shelfwood\LMStudio\ValueObjects\Message;
use Shelfwood\LMStudio\ValueObjects\ToolCall;

beforeEach(function (): void {
    $this->client = Mockery::mock(LMStudioClientInterface::class);
    $this->client->shouldReceive('getConfig->getDefaultModel')->andReturn('test-model');
    $this->client->shouldReceive('getApiVersionNamespace')->andReturn('V1');

    $this->conversation = new Conversation($this->client, 'Test Conversation');
});

test('it can be instantiated', function (): void {
    expect($this->conversation)->toBeInstanceOf(Conversation::class);
});

test('it has a unique ID', function (): void {
    expect($this->conversation->getId())->toBeString();
    expect($this->conversation->getId())->toStartWith('conv_');
});

test('it can be instantiated with a custom ID', function (): void {
    $conversation = new Conversation($this->client, 'Test Conversation', 'custom_id');
    expect($conversation->getId())->toBe('custom_id');
});

test('it can be instantiated with an existing chat history', function (): void {
    $history = new ChatHistory([
        Message::system('You are a helpful assistant.'),
        Message::user('Hello, how are you?'),
    ]);

    $conversation = new Conversation($this->client, 'Test Conversation', null, $history);

    expect($conversation->getMessages())->toHaveCount(2);
    expect($conversation->getMessages()[0]->role)->toBe(Role::SYSTEM);
    expect($conversation->getMessages()[1]->role)->toBe(Role::USER);
});

test('it can get and set the title', function (): void {
    expect($this->conversation->getTitle())->toBe('Test Conversation');

    $result = $this->conversation->setTitle('New Title');
    expect($result)->toBe($this->conversation); // Fluent interface returns $this
    expect($this->conversation->getTitle())->toBe('New Title');
});

test('it has creation and update timestamps', function (): void {
    expect($this->conversation->getCreatedAt())->toBeInstanceOf(\DateTimeImmutable::class);
    expect($this->conversation->getUpdatedAt())->toBeNull();

    // Updating the title should update the timestamp
    $this->conversation->setTitle('New Title');
    expect($this->conversation->getUpdatedAt())->toBeInstanceOf(\DateTimeImmutable::class);
});

test('it can add system messages', function (): void {
    $result = $this->conversation->addSystemMessage('You are a helpful assistant.');

    expect($result)->toBe($this->conversation); // Fluent interface returns $this
    expect($this->conversation->getMessages())->toHaveCount(1);
    expect($this->conversation->getMessages()[0]->role)->toBe(Role::SYSTEM);
    expect($this->conversation->getMessages()[0]->content)->toBe('You are a helpful assistant.');
});

test('it can add user messages', function (): void {
    $result = $this->conversation->addUserMessage('Hello, how are you?');

    expect($result)->toBe($this->conversation); // Fluent interface returns $this
    expect($this->conversation->getMessages())->toHaveCount(1);
    expect($this->conversation->getMessages()[0]->role)->toBe(Role::USER);
    expect($this->conversation->getMessages()[0]->content)->toBe('Hello, how are you?');
});

test('it can add user messages with a name', function (): void {
    $result = $this->conversation->addUserMessage('Hello, how are you?', 'John');

    expect($result)->toBe($this->conversation); // Fluent interface returns $this
    expect($this->conversation->getMessages())->toHaveCount(1);
    expect($this->conversation->getMessages()[0]->role)->toBe(Role::USER);
    expect($this->conversation->getMessages()[0]->content)->toBe('Hello, how are you?');
    expect($this->conversation->getMessages()[0]->name)->toBe('John');
});

test('it can add assistant messages', function (): void {
    $result = $this->conversation->addAssistantMessage('I am doing well, thank you!');

    expect($result)->toBe($this->conversation); // Fluent interface returns $this
    expect($this->conversation->getMessages())->toHaveCount(1);
    expect($this->conversation->getMessages()[0]->role)->toBe(Role::ASSISTANT);
    expect($this->conversation->getMessages()[0]->content)->toBe('I am doing well, thank you!');
});

test('it can add assistant messages with tool calls', function (): void {
    $toolCalls = [
        new ToolCall(
            id: 'call_123',
            type: ToolType::FUNCTION,
            function: new FunctionCall(
                name: 'test_tool',
                arguments: '{"param":"test"}'
            )
        ),
    ];

    $result = $this->conversation->addAssistantMessage('I need to use a tool.', $toolCalls);

    expect($result)->toBe($this->conversation); // Fluent interface returns $this
    expect($this->conversation->getMessages())->toHaveCount(1);
    expect($this->conversation->getMessages()[0]->role)->toBe(Role::ASSISTANT);
    expect($this->conversation->getMessages()[0]->content)->toBe('I need to use a tool.');
    expect($this->conversation->getMessages()[0]->toolCalls)->toBe($toolCalls);
});

test('it can add tool messages', function (): void {
    $result = $this->conversation->addToolMessage('Tool result', 'call_123');

    expect($result)->toBe($this->conversation); // Fluent interface returns $this
    expect($this->conversation->getMessages())->toHaveCount(1);
    expect($this->conversation->getMessages()[0]->role)->toBe(Role::TOOL);
    expect($this->conversation->getMessages()[0]->content)->toBe('Tool result');

    // Check the serialized output instead of the direct property
    $serialized = $this->conversation->getMessages()[0]->jsonSerialize();
    expect($serialized)->toHaveKey('tool_call_id');
    expect($serialized['tool_call_id'])->toBe('call_123');
});

test('it can set a tool registry', function (): void {
    $toolRegistry = new ToolRegistry;
    $result = $this->conversation->setToolRegistry($toolRegistry);

    expect($result)->toBe($this->conversation); // Fluent interface returns $this
});

test('it can set the model', function (): void {
    $result = $this->conversation->setModel('test-model-2');

    expect($result)->toBe($this->conversation); // Fluent interface returns $this
});

test('it can set the temperature', function (): void {
    $result = $this->conversation->setTemperature(0.5);

    expect($result)->toBe($this->conversation); // Fluent interface returns $this
});

test('it can set the max tokens', function (): void {
    $result = $this->conversation->setMaxTokens(100);

    expect($result)->toBe($this->conversation); // Fluent interface returns $this
});

test('it can get a response', function (): void {
    $this->conversation->addSystemMessage('You are a helpful assistant.');
    $this->conversation->addUserMessage('Hello, how are you?');

    // Mock the chatCompletion method
    $this->client->shouldReceive('chatCompletion')
        ->once()
        ->with(Mockery::type('object'))
        ->andReturn((object) [
            'choices' => [
                (object) [
                    'message' => (object) [
                        'content' => 'I am doing well, thank you for asking!',
                        'role' => 'assistant',
                    ],
                ],
            ],
        ]);

    $response = $this->conversation->getResponse();

    expect($response)->toBe('I am doing well, thank you for asking!');

    // Check that the assistant's response was added to the conversation
    $messages = $this->conversation->getMessages();
    expect($messages)->toHaveCount(3);
    expect($messages[2]->role)->toBe(Role::ASSISTANT);
    expect($messages[2]->content)->toBe('I am doing well, thank you for asking!');
});

test('it can get a response with tool calls', function (): void {
    $this->conversation->addSystemMessage('You are a helpful assistant with tools.');
    $this->conversation->addUserMessage('What is 2+2?');

    // Create a tool registry with a calculator tool
    $toolRegistry = new ToolRegistry;
    $toolRegistry->register(
        \Shelfwood\LMStudio\ValueObjects\Tool::function(
            'calculator',
            'Calculate a mathematical expression',
            [
                'expression' => [
                    'type' => 'string',
                    'description' => 'The expression to calculate',
                ],
            ]
        ),
        function ($args) {
            return (string) eval('return '.$args['expression'].';');
        }
    );

    $this->conversation->setToolRegistry($toolRegistry);

    // First response with tool calls
    $this->client->shouldReceive('chatCompletion')
        ->once()
        ->with(Mockery::type('object'))
        ->andReturn((object) [
            'choices' => [
                (object) [
                    'message' => (object) [
                        'content' => 'I need to calculate this.',
                        'role' => 'assistant',
                        'tool_calls' => [
                            (object) [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => (object) [
                                    'name' => 'calculator',
                                    'arguments' => '{"expression":"2+2"}',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

    // Second response after tool execution (no more tool calls)
    $this->client->shouldReceive('chatCompletion')
        ->once()
        ->with(Mockery::type('object'))
        ->andReturn((object) [
            'choices' => [
                (object) [
                    'message' => (object) [
                        'content' => 'The result of 2+2 is 4.',
                        'role' => 'assistant',
                    ],
                ],
            ],
        ]);

    // Call getResponse to trigger the client's chatCompletion method
    $response = $this->conversation->getResponse();

    // The final response should be from the second call
    expect($response)->toBe('The result of 2+2 is 4.');

    // Check that the conversation history contains all messages
    $messages = $this->conversation->getMessages();
    expect($messages)->toHaveCount(5);

    // Check the sequence of messages
    expect($messages[0]->role)->toBe(Role::SYSTEM);
    expect($messages[1]->role)->toBe(Role::USER);
    expect($messages[2]->role)->toBe(Role::ASSISTANT);
    expect($messages[2]->content)->toBe('I need to calculate this.');
    expect($messages[3]->role)->toBe(Role::TOOL);
    expect($messages[4]->role)->toBe(Role::ASSISTANT);
    expect($messages[4]->content)->toBe('The result of 2+2 is 4.');
});

test('it can be serialized to JSON', function (): void {
    $this->conversation->addSystemMessage('You are a helpful assistant.');
    $this->conversation->addUserMessage('Hello, how are you?');

    $json = $this->conversation->toJson();
    expect($json)->toBeString();

    $data = json_decode($json, true);
    expect($data)->toBeArray();
    expect($data)->toHaveKey('id');
    expect($data)->toHaveKey('title');
    expect($data)->toHaveKey('messages');
    expect($data['messages'])->toHaveCount(2);
});

test('it can be created from JSON', function (): void {
    $this->conversation->addSystemMessage('You are a helpful assistant.');
    $this->conversation->addUserMessage('Hello, how are you?');

    $json = $this->conversation->toJson();

    $newConversation = Conversation::fromJson($json, $this->client);

    expect($newConversation)->toBeInstanceOf(Conversation::class);
    expect($newConversation->getId())->toBe($this->conversation->getId());
    expect($newConversation->getTitle())->toBe($this->conversation->getTitle());
    expect($newConversation->getMessages())->toHaveCount(2);
});

test('it can get and set metadata', function (): void {
    expect($this->conversation->getMetadata('test_key'))->toBeNull();
    expect($this->conversation->getMetadata('test_key', 'default'))->toBe('default');

    $result = $this->conversation->setMetadata('test_key', 'test_value');

    expect($result)->toBe($this->conversation); // Fluent interface returns $this
    expect($this->conversation->getMetadata('test_key'))->toBe('test_value');
});
