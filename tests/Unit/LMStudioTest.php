<?php

declare(strict_types=1);

namespace Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Chat\Role;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\DTOs\Model\ModelInfo;
use Shelfwood\LMStudio\DTOs\Model\ModelList;
use Shelfwood\LMStudio\DTOs\Tool\ToolCall;
use Shelfwood\LMStudio\DTOs\Tool\ToolFunction;
use Shelfwood\LMStudio\LMStudio;
use Tests\TestCase;

class LMStudioTest extends TestCase
{
    protected MockHandler $mockHandler;

    protected LMStudio $lmstudio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($this->mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $this->lmstudio = new LMStudio(new Config(
            host: 'localhost',
            port: 1234,
            timeout: 30
        ));

        // Replace the client with our mocked version
        $reflection = new \ReflectionClass($this->lmstudio);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($this->lmstudio, $client);
    }

    public function test_it_can_be_instantiated_with_default_config(): void
    {
        $lmstudio = new LMStudio;
        $config = $lmstudio->getConfig();

        expect($config->host)->toBe('localhost')
            ->and($config->port)->toBe(1234)
            ->and($config->timeout)->toBe(30);
    }

    public function test_it_can_list_models(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'object' => 'list',
            'data' => [
                [
                    'id' => 'model-1',
                    'object' => 'model',
                    'created' => 1234567890,
                    'owned_by' => 'owner',
                ],
            ],
        ])));

        $result = $this->lmstudio->listModels();

        expect($result)->toBeInstanceOf(ModelList::class)
            ->and($result->object)->toBe('list')
            ->and($result->data)->toHaveCount(1)
            ->and($result->data[0])->toBeInstanceOf(ModelInfo::class)
            ->and($result->data[0]->id)->toBe('model-1');
    }

    public function test_it_can_get_model_information(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'id' => 'model-1',
            'object' => 'model',
            'created' => 1234567890,
            'owned_by' => 'owner',
        ])));

        $result = $this->lmstudio->getModel('model-1');

        expect($result)->toBeInstanceOf(ModelInfo::class)
            ->and($result->id)->toBe('model-1')
            ->and($result->object)->toBe('model')
            ->and($result->created)->toBe(1234567890)
            ->and($result->ownedBy)->toBe('owner');
    }

    public function test_it_can_create_chat_completion(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'id' => 'chat-1',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello!',
                    ],
                ],
            ],
        ])));

        $result = $this->lmstudio->createChatCompletion(
            messages: [new Message(Role::USER, 'Hi!')],
            model: 'test-model'
        );

        expect($result->id)->toBe('chat-1')
            ->and($result->object)->toBe('chat.completion')
            ->and($result->choices[0]->message->content)->toBe('Hello!');
    }

    public function test_it_can_stream_chat_completion(): void
    {
        $events = [
            json_encode(['choices' => [['delta' => ['content' => 'Hello']]]]).\PHP_EOL,
            json_encode(['choices' => [['delta' => ['content' => ' world!']]]]).\PHP_EOL,
            '[DONE]'.\PHP_EOL,
        ];

        $this->mockHandler->append(new Response(200, [], implode('', $events)));

        $messages = [];

        foreach ($this->lmstudio->createChatCompletion(
            messages: [new Message(Role::USER, 'Hi!')],
            model: 'test-model',
            stream: true
        ) as $message) {
            $messages[] = $message;
        }

        expect($messages)->toHaveCount(2)
            ->and($messages[0])->toBeInstanceOf(Message::class)
            ->and($messages[0]->content)->toBe('Hello')
            ->and($messages[1]->content)->toBe(' world!');
    }

    public function test_it_can_stream_chat_completion_with_tool_calls(): void
    {
        $weatherTool = new ToolFunction(
            name: 'get_current_weather',
            description: 'Get the current weather',
            parameters: [
                'location' => [
                    'type' => 'string',
                    'description' => 'The location to get weather for',
                ],
            ],
            required: ['location']
        );

        $events = [
            json_encode([
                'choices' => [[
                    'delta' => [
                        'tool_calls' => [[
                            'id' => '123',
                            'type' => 'function',
                            'function' => ['name' => 'get_current_weather'],
                        ]],
                    ],
                ]],
            ]).\PHP_EOL,
            json_encode([
                'choices' => [[
                    'delta' => [
                        'tool_calls' => [[
                            'function' => ['arguments' => '{"location":"London"}'],
                        ]],
                    ],
                ]],
            ]).\PHP_EOL,
            '[DONE]'.\PHP_EOL,
        ];

        $this->mockHandler->append(new Response(200, [], implode('', $events)));

        $messages = [];

        foreach ($this->lmstudio->createChatCompletion(
            messages: [new Message(Role::USER, 'What\'s the weather in London?')],
            model: 'test-model',
            tools: [new ToolCall(uniqid('call_'), 'function', $weatherTool)],
            stream: true
        ) as $message) {
            $messages[] = $message;
        }

        expect($messages)
            ->toHaveCount(1)
            ->and($messages[0])->toBeInstanceOf(ToolCall::class)
            ->and($messages[0]->function->name)->toBe('get_current_weather')
            ->and($messages[0]->arguments)->toBe('{"location":"London"}');
    }
}
