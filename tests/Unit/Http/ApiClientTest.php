<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\Exceptions\ConnectionException;
use Shelfwood\LMStudio\Http\ApiClient;

beforeEach(function (): void {
    $this->mockHandler = new MockHandler;
    $handlerStack = HandlerStack::create($this->mockHandler);

    $this->client = new ApiClient([
        'handler' => $handlerStack,
    ]);
});

test('it can make get request', function (): void {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'key' => 'value',
    ])));

    $result = $this->client->get('/test');

    expect($result)->toBe(['key' => 'value']);
});

test('it can make post request', function (): void {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'key' => 'value',
    ])));

    $result = $this->client->post('/test', [
        'json' => ['data' => 'test'],
    ]);

    expect($result)->toBe(['key' => 'value']);
});

test('it can handle streaming response', function (): void {
    $this->mockHandler->append(new Response(200, [], 'stream data'));

    $result = $this->client->post('/test', [
        'stream' => true,
    ]);

    expect($result)->toBeInstanceOf(\Psr\Http\Message\ResponseInterface::class)
        ->and($result->getBody()->getContents())->toBe('stream data');
});

test('it throws exception on invalid json', function (): void {
    $this->mockHandler->append(new Response(200, [], 'invalid json'));

    expect(fn () => $this->client->get('/test'))
        ->toThrow(ConnectionException::class, 'Response is not a valid JSON');
});

test('it throws exception on get request failure', function (): void {
    $this->mockHandler->append(new Response(500, [], 'Server Error'));

    expect(fn () => $this->client->get('/test'))
        ->toThrow(ConnectionException::class, "GET request to '/test' failed");
});

test('it throws exception on post request failure', function (): void {
    $this->mockHandler->append(new Response(500, [], 'Server Error'));

    expect(fn () => $this->client->post('/test'))
        ->toThrow(ConnectionException::class, "POST request to '/test' failed");
});
