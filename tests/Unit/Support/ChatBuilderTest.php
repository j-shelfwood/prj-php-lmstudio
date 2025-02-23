<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Chat\Role;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\DTOs\Tool\ToolCall;
use Shelfwood\LMStudio\DTOs\Tool\ToolFunction;
use Shelfwood\LMStudio\Exceptions\ValidationException;
use Shelfwood\LMStudio\LMStudio;
use Tests\TestCase;

class ChatBuilderTest extends TestCase
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

    public function test_it_can_be_instantiated(): void
    {
        $chat = $this->lmstudio->chat();
        expect($chat)->toBeObject();
    }

    public function test_it_can_set_model(): void
    {
        $chat = $this->lmstudio->chat()->withModel('test-model');
        expect($chat)->toBeObject();

        $this->expectException(ValidationException::class);
        $this->lmstudio->chat()->withModel('');
    }

    public function test_it_can_set_messages(): void
    {
        $messages = [
            new Message(Role::SYSTEM, 'System message'),
            new Message(Role::USER, 'User message'),
        ];

        $chat = $this->lmstudio->chat()->withMessages($messages);
        expect($chat)->toBeObject();

        // Test with array format
        $chat = $this->lmstudio->chat()->withMessages([
            ['role' => 'system', 'content' => 'System message'],
            ['role' => 'user', 'content' => 'User message'],
        ]);
        expect($chat)->toBeObject();
    }

    public function test_it_can_add_a_single_message(): void
    {
        $chat = $this->lmstudio->chat()->addMessage(Role::USER, 'Test message');
        expect($chat)->toBeObject();

        $this->expectException(ValidationException::class);
        $this->lmstudio->chat()->addMessage(Role::USER, '');
    }

    public function test_it_can_set_tools(): void
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

        $chat = $this->lmstudio->chat()->withTools([$weatherTool]);
        expect($chat)->toBeObject();

        // Test with array format
        $chat = $this->lmstudio->chat()->withTools([
            [
                'id' => 'test-id',
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_weather',
                    'description' => 'Get the current weather',
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
                ],
            ],
        ]);
        expect($chat)->toBeObject();
    }

    public function test_it_can_register_tool_handlers(): void
    {
        $chat = $this->lmstudio->chat()->withToolHandler('test', fn () => true);
        expect($chat)->toBeObject();

        $this->expectException(ValidationException::class);
        $this->lmstudio->chat()->withToolHandler('', fn () => true);
    }

    public function test_it_can_send_chat_completion(): void
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

        $result = $this->lmstudio->chat()
            ->withModel('test-model')
            ->withMessages([new Message(Role::USER, 'Hi!')])
            ->send();

        expect($result->id)->toBe('chat-1')
            ->and($result->object)->toBe('chat.completion')
            ->and($result->choices[0]->message->content)->toBe('Hello!');

        $this->expectException(ValidationException::class);
        $this->lmstudio->chat()->send();
    }

    public function test_it_can_stream_chat_completion(): void
    {
        $events = [
            json_encode([
                'choices' => [[
                    'delta' => ['content' => 'Hello'],
                ]],
            ]).\PHP_EOL,
            json_encode([
                'choices' => [[
                    'delta' => ['content' => ' world!'],
                ]],
            ]).\PHP_EOL,
            '[DONE]'.\PHP_EOL,
        ];

        $this->mockHandler->append(new Response(200, [], implode('', $events)));

        $messages = [];

        foreach ($this->lmstudio->chat()
            ->withModel('test-model')
            ->withMessages([new Message(Role::USER, 'Hi!')])
            ->stream()
            ->send() as $message) {
            $messages[] = $message;
        }

        expect($messages)->toHaveCount(2)
            ->and($messages[0])->toBeInstanceOf(Message::class)
            ->and($messages[0]->content)->toBe('Hello')
            ->and($messages[1]->content)->toBe(' world!');
    }

    public function test_it_can_handle_tool_calls(): void
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

        foreach ($this->lmstudio->chat()
            ->withModel('test-model')
            ->withMessages([new Message(Role::USER, 'What\'s the weather in London?')])
            ->withTools([$weatherTool])
            ->withToolHandler('get_current_weather', function (array $args) {
                return ['temperature' => 20, 'condition' => 'sunny'];
            })
            ->stream()
            ->send() as $message) {
            $messages[] = $message;
        }

        expect($messages)->toHaveCount(1)
            ->and($messages[0])->toBeInstanceOf(ToolCall::class)
            ->and($messages[0]->function->name)->toBe('get_current_weather');

        $args = $messages[0]->function->validateArguments('{"location":"London"}');
        expect($args)->toBe(['location' => 'London']);
    }
}
