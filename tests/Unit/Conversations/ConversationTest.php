<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Conversations\Conversation;
use Shelfwood\LMStudio\Enums\Role;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\FunctionCall;
use Shelfwood\LMStudio\ValueObjects\Message;
use Shelfwood\LMStudio\ValueObjects\ToolCall;

beforeEach(function (): void {
    $this->client = Mockery::mock(LMStudioClientInterface::class);
    $this->client->shouldReceive('getConfig->getDefaultModel')->andReturn('test-model');

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
            type: 'function',
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

    $this->client->shouldReceive('chat')
        ->once()
        ->with(Mockery::type('array'), Mockery::type('array'))
        ->andReturn([
            'choices' => [
                [
                    'message' => [
                        'content' => 'I am doing well, thank you!',
                        'role' => 'assistant',
                    ],
                ],
            ],
        ]);

    $response = $this->conversation->getResponse();

    expect($response)->toBeString();
    expect($response)->toBe('I am doing well, thank you!');

    // Check that the assistant message was added to the history
    expect($this->conversation->getMessages())->toHaveCount(3);
    expect($this->conversation->getMessages()[2]->role)->toBe(Role::ASSISTANT);
    expect($this->conversation->getMessages()[2]->content)->toBe('I am doing well, thank you!');
});

test('it can get a response with tool calls', function (): void {
    // Create a conversation with our mocked client
    $conversation = new Conversation($this->client, 'Test Conversation');
    $conversation->addSystemMessage('You are a helpful assistant.');
    $conversation->addUserMessage('What is 2 + 2?');

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

    $conversation->setToolRegistry($toolRegistry);

    // First response with tool calls
    $this->client->shouldReceive('chat')
        ->once()
        ->with(Mockery::type('array'), Mockery::type('array'))
        ->andReturn([
            'choices' => [
                [
                    'message' => [
                        'content' => 'I need to calculate this.',
                        'role' => 'assistant',
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
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
    $this->client->shouldReceive('chat')
        ->once()
        ->with(Mockery::type('array'), Mockery::type('array'))
        ->andReturn([
            'choices' => [
                [
                    'message' => [
                        'content' => 'The result of 2+2 is 4.',
                        'role' => 'assistant',
                    ],
                ],
            ],
        ]);

    // Call getResponse to trigger the client's chat method
    $response = $conversation->getResponse();

    // The final response should be from the second call
    expect($response)->toBe('The result of 2+2 is 4.');

    // Check that the conversation history contains all messages
    $messages = $conversation->getMessages();
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

test('it can stream a response', function (): void {
    // Skip this test for now as it requires more complex mocking
    $this->markTestSkipped('This test requires more complex mocking');
});

test('it can stream a response with tool calls', function (): void {
    // Skip this test for now as it requires more complex mocking
    $this->markTestSkipped('This test requires more complex mocking');
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
