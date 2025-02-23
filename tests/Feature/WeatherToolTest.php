<?php

declare(strict_types=1);

namespace Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\DTOs\Chat\Message;
use Shelfwood\LMStudio\DTOs\Chat\Role;
use Shelfwood\LMStudio\DTOs\Common\Config;
use Shelfwood\LMStudio\DTOs\Tool\ToolCall;
use Shelfwood\LMStudio\DTOs\Tool\ToolFunction;
use Shelfwood\LMStudio\Http\ApiClient;
use Shelfwood\LMStudio\LMStudio;
use Tests\TestCase;

class WeatherToolTest extends TestCase
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
        $property = $reflection->getProperty('apiClient');
        $property->setAccessible(true);
        $property->setValue($this->lmstudio, new ApiClient(['handler' => $handlerStack]));
    }

    public function test_it_can_get_weather_for_a_location(): void
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

    public function test_it_handles_multiple_weather_requests_in_a_conversation(): void
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
            json_encode([
                'choices' => [[
                    'delta' => [
                        'tool_calls' => [[
                            'id' => '456',
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
                            'function' => ['arguments' => '{"location":"Paris"}'],
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
            ->withMessages([new Message(Role::USER, 'Compare the weather in London and Paris')])
            ->withTools([$weatherTool])
            ->withToolHandler('get_current_weather', function (array $args) {
                return [
                    'temperature' => $args['location'] === 'London' ? 20 : 25,
                    'condition' => $args['location'] === 'London' ? 'sunny' : 'cloudy',
                ];
            })
            ->stream()
            ->send() as $message) {
            $messages[] = $message;
        }

        expect($messages)->toHaveCount(2)
            ->and($messages[0])->toBeInstanceOf(ToolCall::class)
            ->and($messages[0]->function->name)->toBe('get_current_weather')
            ->and($messages[1])->toBeInstanceOf(ToolCall::class)
            ->and($messages[1]->function->name)->toBe('get_current_weather');

        $args1 = $messages[0]->function->validateArguments('{"location":"London"}');
        $args2 = $messages[1]->function->validateArguments('{"location":"Paris"}');

        expect($args1)->toBe(['location' => 'London'])
            ->and($args2)->toBe(['location' => 'Paris']);
    }
}
