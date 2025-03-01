<?php

declare(strict_types=1);

namespace Tests\Unit\Requests\V1;

use Shelfwood\LMStudio\Enums\Role;
use Shelfwood\LMStudio\Requests\V1\ChatCompletionRequest;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\JsonSchema;
use Shelfwood\LMStudio\ValueObjects\Message;
use Shelfwood\LMStudio\ValueObjects\Tool;

test('it can be instantiated with messages array', function (): void {
    $messages = [
        new Message(Role::USER, 'Hello'),
    ];
    $request = new ChatCompletionRequest($messages, 'gpt-3.5-turbo');

    expect($request)->toBeInstanceOf(ChatCompletionRequest::class);

    $data = $request->toArray();
    expect($data)->toHaveKey('messages')
        ->and($data['messages'])->toHaveCount(1)
        ->and($data['messages'][0]['role'])->toBe('user')
        ->and($data['messages'][0]['content'])->toBe('Hello');
});

test('it can be instantiated with chat history', function (): void {
    $history = new ChatHistory([
        new Message(Role::USER, 'Hello'),
    ]);
    $request = new ChatCompletionRequest($history, 'gpt-3.5-turbo');

    expect($request)->toBeInstanceOf(ChatCompletionRequest::class);

    $data = $request->toArray();
    expect($data)->toHaveKey('messages')
        ->and($data['messages'])->toHaveCount(1)
        ->and($data['messages'][0]['role'])->toBe('user')
        ->and($data['messages'][0]['content'])->toBe('Hello');
});

test('it can be instantiated with message arrays', function (): void {
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
    ];
    $request = new ChatCompletionRequest($messages, 'gpt-3.5-turbo');

    expect($request)->toBeInstanceOf(ChatCompletionRequest::class);

    $data = $request->toArray();
    expect($data)->toHaveKey('messages')
        ->and($data['messages'])->toHaveCount(1)
        ->and($data['messages'][0]['role'])->toBe('user')
        ->and($data['messages'][0]['content'])->toBe('Hello');
});

test('it can set temperature', function (): void {
    $messages = [
        new Message(Role::USER, 'Hello'),
    ];
    $request = new ChatCompletionRequest($messages, 'gpt-3.5-turbo');
    $newRequest = $request->withTemperature(0.5);

    expect($newRequest)->not->toBe($request)
        ->and($newRequest->toArray()['temperature'])->toBe(0.5);
});

test('it can set max tokens', function (): void {
    $messages = [
        new Message(Role::USER, 'Hello'),
    ];
    $request = new ChatCompletionRequest($messages, 'gpt-3.5-turbo');
    $newRequest = $request->withMaxTokens(100);

    expect($newRequest)->not->toBe($request)
        ->and($newRequest->toArray()['max_tokens'])->toBe(100);
});

test('it can enable streaming', function (): void {
    $messages = [
        new Message(Role::USER, 'Hello'),
    ];
    $request = new ChatCompletionRequest($messages, 'gpt-3.5-turbo');
    $newRequest = $request->withStreaming();

    expect($newRequest)->not->toBe($request)
        ->and($newRequest->toArray()['stream'])->toBeTrue();
});

test('it can add tools', function (): void {
    $messages = [
        new Message(Role::USER, 'Hello'),
    ];
    $request = new ChatCompletionRequest($messages, 'gpt-3.5-turbo');

    $tool = Tool::function(
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

    $newRequest = $request->withTools([$tool]);

    expect($newRequest)->not->toBe($request);

    $data = $newRequest->toArray();
    expect($data)->toHaveKey('tools')
        ->and($data['tools'])->toHaveCount(1)
        ->and($data['tools'][0]['type'])->toBe('function')
        ->and($data['tools'][0]['function']['name'])->toBe('get_weather')
        ->and($data['tools'][0]['function']['description'])->toBe('Get the current weather in a given location')
        ->and($data['tools'][0]['function']['parameters']['properties']['location']['type'])->toBe('string')
        ->and($data['tools'][0]['function']['parameters']['required'])->toContain('location');
});

test('it can set tool choice to auto', function (): void {
    $messages = [
        new Message(Role::USER, 'Hello'),
    ];
    $request = new ChatCompletionRequest($messages, 'gpt-3.5-turbo');
    $newRequest = $request->withToolChoice('auto');

    expect($newRequest)->not->toBe($request)
        ->and($newRequest->toArray()['tool_choice'])->toBe('auto');
});

test('it can set tool choice to none', function (): void {
    $messages = [
        new Message(Role::USER, 'Hello'),
    ];
    $request = new ChatCompletionRequest($messages, 'gpt-3.5-turbo');
    $newRequest = $request->withToolChoice('none');

    expect($newRequest)->not->toBe($request)
        ->and($newRequest->toArray()['tool_choice'])->toBe('none');
});

test('it can set tool choice to a specific function', function (): void {
    $messages = [
        new Message(Role::USER, 'Hello'),
    ];
    $request = new ChatCompletionRequest($messages, 'gpt-3.5-turbo');
    $newRequest = $request->withToolChoice([
        'type' => 'function',
        'function' => ['name' => 'get_weather'],
    ]);

    expect($newRequest)->not->toBe($request);

    $data = $newRequest->toArray();
    expect($data)->toHaveKey('tool_choice')
        ->and($data['tool_choice']['type'])->toBe('function')
        ->and($data['tool_choice']['function']['name'])->toBe('get_weather');
});

test('it can set response format with name and strict parameters', function (): void {
    $schema = [
        'type' => 'object',
        'properties' => [
            'joke' => [
                'type' => 'string',
            ],
        ],
        'required' => ['joke'],
    ];

    $request = new ChatCompletionRequest([], 'gpt-3.5-turbo');
    $newRequest = $request->withResponseFormat($schema, 'joke_schema', true);

    $data = $newRequest->toArray();
    expect($data)->toHaveKey('response_format')
        ->and($data['response_format']['type'])->toBe('json_schema')
        ->and($data['response_format']['json_schema']['schema'])->toBe($schema)
        ->and($data['response_format']['json_schema']['name'])->toBe('joke_schema')
        ->and($data['response_format']['json_schema']['strict'])->toBeTrue();
});

test('it can set response format with JsonSchema value object', function (): void {
    $jsonSchema = JsonSchema::keyValue('joke', 'string', 'A funny joke', 'joke_schema', true);
    $request = new ChatCompletionRequest([], 'gpt-3.5-turbo');
    $newRequest = $request->withResponseFormat($jsonSchema);

    $data = $newRequest->toArray();
    expect($data)->toHaveKey('response_format')
        ->and($data['response_format']['type'])->toBe('json_schema')
        ->and($data['response_format']['json_schema']['schema'])->toBe([
            'type' => 'object',
            'properties' => [
                'joke' => [
                    'type' => 'string',
                    'description' => 'A funny joke',
                ],
            ],
            'required' => ['joke'],
        ])
        ->and($data['response_format']['json_schema']['name'])->toBe('joke_schema')
        ->and($data['response_format']['json_schema']['strict'])->toBeTrue();
});

test('it can set TTL for the model', function (): void {
    $messages = [
        new Message(Role::USER, 'Hello'),
    ];
    $request = new ChatCompletionRequest($messages, 'gpt-3.5-turbo');
    $newRequest = $request->withTtl(300);

    expect($newRequest)->not->toBe($request)
        ->and($newRequest->toArray()['ttl'])->toBe(300);
});

test('it can set JIT loading for the model', function (): void {
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
    ];
    $request = new ChatCompletionRequest($messages, 'test-model');

    $newRequest = $request->withJit(true);

    // Ensure the original request is not modified
    expect($newRequest)->not->toBe($request);

    // Check that the JIT flag is set correctly in the serialized output
    $serialized = $newRequest->jsonSerialize();
    expect($serialized['jit'])->toBeTrue();

    // Test with JIT disabled
    $disabledRequest = $request->withJit(false);
    $serialized = $disabledRequest->jsonSerialize();
    expect($serialized['jit'])->toBeFalse();
});
