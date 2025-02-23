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
use Shelfwood\LMStudio\Exceptions\ValidationException;
use Shelfwood\LMStudio\Http\ApiClient;
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

        // Replace the apiClient with our mocked version
        $reflection = new \ReflectionClass($this->lmstudio);

        $apiClientProperty = $reflection->getProperty('apiClient');
        $apiClientProperty->setAccessible(true);
        $apiClientProperty->setValue($this->lmstudio, new ApiClient(['handler' => $handlerStack]));
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

    public function test_it_can_create_text_completion(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'id' => 'cmpl-1',
            'object' => 'text_completion',
            'created' => 1234567890,
            'choices' => [
                [
                    'text' => 'Hello world!',
                    'index' => 0,
                    'finish_reason' => 'stop',
                ],
            ],
        ])));

        $result = $this->lmstudio->createTextCompletion(
            prompt: 'Say hello',
            model: 'test-model'
        );

        expect($result['id'])->toBe('cmpl-1')
            ->and($result['object'])->toBe('text_completion')
            ->and($result['choices'][0]['text'])->toBe('Hello world!');
    }

    public function test_it_can_stream_chat_completion(): void
    {
        $events = [
            'data: '.json_encode(['choices' => [['delta' => ['content' => 'Hello']]]]).\PHP_EOL,
            'data: '.json_encode(['choices' => [['delta' => ['content' => ' world!']]]]).\PHP_EOL,
            'data: [DONE]'.\PHP_EOL,
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

    public function test_it_can_stream_text_completion(): void
    {
        $events = [
            'data: '.json_encode([
                'choices' => [[
                    'delta' => ['content' => 'Hello'],
                ]],
            ]).\PHP_EOL,
            'data: '.json_encode([
                'choices' => [[
                    'delta' => ['content' => ' world!'],
                ]],
            ]).\PHP_EOL,
            'data: [DONE]'.\PHP_EOL,
        ];

        $this->mockHandler->append(new Response(200, [], implode('', $events)));

        $messages = [];

        foreach ($this->lmstudio->createTextCompletion(
            prompt: 'Say hello',
            model: 'test-model',
            options: ['stream' => true]
        ) as $message) {
            $messages[] = $message;
        }

        expect($messages)->toHaveCount(2)
            ->and($messages[0]->content)->toBe('Hello')
            ->and($messages[1]->content)->toBe(' world!');
    }

    public function test_it_fails_text_completion_with_empty_prompt(): void
    {
        expect(fn () => $this->lmstudio->createTextCompletion(
            prompt: '',
            model: 'test-model'
        ))->toThrow(ValidationException::class, 'Prompt cannot be empty for text completion');
    }

    public function test_it_fails_text_completion_with_empty_model(): void
    {
        expect(fn () => $this->lmstudio->createTextCompletion(
            prompt: 'Say hello'
        ))->toThrow(ValidationException::class, 'Model must be specified for text completion');
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
            'data: '.json_encode([
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
            'data: '.json_encode([
                'choices' => [[
                    'delta' => [
                        'tool_calls' => [[
                            'function' => ['arguments' => '{"location":"London"}'],
                        ]],
                    ],
                ]],
            ]).\PHP_EOL,
            'data: [DONE]'.\PHP_EOL,
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

    // -------------------------------
    // REST API Tests
    // -------------------------------

    public function test_it_can_list_rest_models(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'object' => 'list',
            'data' => [
                [
                    'id' => 'model-1',
                    'object' => 'model',
                    'type' => 'llm',
                    'publisher' => 'test',
                    'arch' => 'test',
                    'compatibility_type' => 'gguf',
                    'quantization' => 'Q4_K_M',
                    'state' => 'loaded',
                    'max_context_length' => 4096,
                ],
            ],
        ])));

        $result = $this->lmstudio->listRestModels();

        expect($result['object'])->toBe('list')
            ->and($result['data'])->toHaveCount(1)
            ->and($result['data'][0]['id'])->toBe('model-1')
            ->and($result['data'][0]['state'])->toBe('loaded');
    }

    public function test_it_can_get_rest_model(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'id' => 'model-1',
            'object' => 'model',
            'type' => 'llm',
            'publisher' => 'test',
            'arch' => 'test',
            'compatibility_type' => 'gguf',
            'quantization' => 'Q4_K_M',
            'state' => 'loaded',
            'max_context_length' => 4096,
        ])));

        $result = $this->lmstudio->getRestModel('model-1');

        expect($result['id'])->toBe('model-1')
            ->and($result['state'])->toBe('loaded')
            ->and($result['max_context_length'])->toBe(4096);
    }

    public function test_it_fails_get_rest_model_with_empty_model(): void
    {
        expect(fn () => $this->lmstudio->getRestModel(''))
            ->toThrow(ValidationException::class, 'Model identifier cannot be empty for REST API');
    }

    public function test_it_can_create_rest_chat_completion(): void
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
            'stats' => [
                'tokens_per_second' => 51.43,
                'time_to_first_token' => 0.111,
                'generation_time' => 0.954,
                'stop_reason' => 'eosFound',
            ],
        ])));

        $result = $this->lmstudio->createRestChatCompletion(
            messages: [new Message(Role::USER, 'Hi!')],
            model: 'test-model'
        );

        expect($result['id'])->toBe('chat-1')
            ->and($result['object'])->toBe('chat.completion')
            ->and($result['choices'][0]['message']['content'])->toBe('Hello!')
            ->and($result['stats']['tokens_per_second'])->toBe(51.43);
    }

    public function test_it_can_create_rest_completion(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'id' => 'cmpl-1',
            'object' => 'text_completion',
            'created' => 1234567890,
            'choices' => [
                [
                    'text' => 'Hello world!',
                    'index' => 0,
                    'finish_reason' => 'stop',
                ],
            ],
            'stats' => [
                'tokens_per_second' => 57.69,
                'time_to_first_token' => 0.299,
                'generation_time' => 0.156,
                'stop_reason' => 'maxPredictedTokensReached',
            ],
        ])));

        $result = $this->lmstudio->createRestCompletion(
            prompt: 'Say hello',
            model: 'test-model'
        );

        expect($result['id'])->toBe('cmpl-1')
            ->and($result['object'])->toBe('text_completion')
            ->and($result['choices'][0]['text'])->toBe('Hello world!')
            ->and($result['stats']['tokens_per_second'])->toBe(57.69);
    }

    public function test_it_can_create_rest_embeddings(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'object' => 'list',
            'data' => [
                [
                    'object' => 'embedding',
                    'embedding' => [-0.016731, 0.028460, -0.140783],
                    'index' => 0,
                ],
            ],
            'model' => 'test-model',
            'usage' => [
                'prompt_tokens' => 5,
                'total_tokens' => 5,
            ],
        ])));

        $result = $this->lmstudio->createRestEmbeddings(
            model: 'test-model',
            input: 'Test text'
        );

        expect($result['object'])->toBe('list')
            ->and($result['data'])->toHaveCount(1)
            ->and($result['data'][0]['embedding'])->toBeArray()
            ->and($result['data'][0]['embedding'])->toHaveCount(3);
    }

    public function test_it_fails_rest_embeddings_with_empty_input(): void
    {
        expect(fn () => $this->lmstudio->createRestEmbeddings(
            model: 'test-model',
            input: ''
        ))->toThrow(ValidationException::class, 'Input cannot be empty for REST embeddings');
    }

    public function test_it_fails_rest_embeddings_with_empty_model(): void
    {
        expect(fn () => $this->lmstudio->createRestEmbeddings(
            model: '',
            input: 'Test text'
        ))->toThrow(ValidationException::class, 'Model identifier cannot be empty for REST embeddings');
    }
}
