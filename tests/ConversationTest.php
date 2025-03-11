<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\Conversations\Conversation;
use Shelfwood\LMStudio\Tools\ToolRegistry;
use Shelfwood\LMStudio\ValueObjects\Message;
use Shelfwood\LMStudio\ValueObjects\Tool;

class ConversationTest extends TestCase
{
    /**
     * Test creating a conversation with the static factory method.
     */
    public function test_create_conversation_with_system_message(): void
    {
        // Create a mock client
        /** @var LMStudioClientInterface&MockObject $client */
        $client = $this->createMock(LMStudioClientInterface::class);
        /** @var LMStudioConfig&MockObject $config */
        $config = $this->createMock(LMStudioConfig::class);
        $config->method('getDefaultModel')->willReturn('qwen2.5-7b-instruct-1m');
        $client->method('getConfig')->willReturn($config);

        // Create a conversation with the static factory method
        $conversation = Conversation::withSystemMessage(
            $client,
            'You are a helpful assistant.'
        );

        // Assert that the conversation was created correctly
        $this->assertInstanceOf(Conversation::class, $conversation);
        $this->assertEquals('You are a helpful assistant.', $conversation->getHistory()->getMessages()[0]->content);
    }

    /**
     * Test creating a conversation with tools.
     */
    public function test_create_conversation_with_tools(): void
    {
        // Create a mock client
        /** @var LMStudioClientInterface&MockObject $client */
        $client = $this->createMock(LMStudioClientInterface::class);
        /** @var LMStudioConfig&MockObject $config */
        $config = $this->createMock(LMStudioConfig::class);
        $config->method('getDefaultModel')->willReturn('qwen2.5-7b-instruct-1m');
        $client->method('getConfig')->willReturn($config);

        // Create a tool registry
        $toolRegistry = new ToolRegistry;

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
            return '42';
        });

        // Create a conversation with tools
        $conversation = Conversation::withTools(
            $client,
            $toolRegistry,
            'You are a helpful assistant.'
        );

        // Assert that the conversation was created correctly
        $this->assertInstanceOf(Conversation::class, $conversation);
        $this->assertTrue($conversation->hasTools());
        $this->assertCount(1, $conversation->getToolRegistry()->getTools());
    }

    /**
     * Test the send method.
     */
    public function test_send_method(): void
    {
        // Create a mock client
        /** @var LMStudioClientInterface&MockObject $client */
        $client = $this->createMock(LMStudioClientInterface::class);
        /** @var LMStudioConfig&MockObject $config */
        $config = $this->createMock(LMStudioConfig::class);
        $config->method('getDefaultModel')->willReturn('qwen2.5-7b-instruct-1m');
        $client->method('getConfig')->willReturn($config);
        $client->method('getApiVersionNamespace')->willReturn('V1');

        // Mock the chatCompletion method
        $response = new \stdClass;
        $response->choices = [
            (object) [
                'message' => (object) [
                    'content' => 'This is a test response.',
                ],
            ],
        ];

        $client->method('chatCompletion')->willReturn($response);

        // Create a conversation
        $conversation = new Conversation($client);

        // Send a message
        $result = $conversation->send('Hello, world!');

        // Assert that the message was sent and a response was received
        $this->assertEquals('This is a test response.', $result);
    }

    /**
     * Test the builder pattern.
     */
    public function test_builder_pattern(): void
    {
        // Create a mock client
        /** @var LMStudioClientInterface&MockObject $client */
        $client = $this->createMock(LMStudioClientInterface::class);
        /** @var LMStudioConfig&MockObject $config */
        $config = $this->createMock(LMStudioConfig::class);
        $config->method('getDefaultModel')->willReturn('qwen2.5-7b-instruct-1m');
        $client->method('getConfig')->willReturn($config);

        // Create a conversation with the builder pattern
        $conversation = Conversation::builder($client)
            ->withTitle('Test Conversation')
            ->withModel('qwen2.5-7b-instruct-1m')
            ->withTemperature(0.8)
            ->withSystemMessage('You are a helpful assistant.')
            ->withUserMessage('Hello, world!')
            ->build();

        // Assert that the conversation was created correctly
        $this->assertInstanceOf(Conversation::class, $conversation);
        $this->assertEquals('Test Conversation', $conversation->getTitle());
        $this->assertEquals('qwen2.5-7b-instruct-1m', $conversation->getModel());
        $this->assertEquals(0.8, $conversation->getTemperature());
        $this->assertCount(2, $conversation->getMessages());
    }
}
