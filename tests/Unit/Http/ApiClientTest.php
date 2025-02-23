<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Shelfwood\LMStudio\Exceptions\ConnectionException;
use Shelfwood\LMStudio\Http\ApiClient;
use Tests\TestCase;

class ApiClientTest extends TestCase
{
    protected MockHandler $mockHandler;

    protected ApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($this->mockHandler);

        $this->client = new ApiClient([
            'handler' => $handlerStack,
        ]);
    }

    public function test_it_can_make_get_request(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'key' => 'value',
        ])));

        $result = $this->client->get('/test');

        expect($result)->toBe(['key' => 'value']);
    }

    public function test_it_can_make_post_request(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'key' => 'value',
        ])));

        $result = $this->client->post('/test', [
            'json' => ['data' => 'test'],
        ]);

        expect($result)->toBe(['key' => 'value']);
    }

    public function test_it_can_handle_streaming_response(): void
    {
        $this->mockHandler->append(new Response(200, [], 'stream data'));

        $result = $this->client->post('/test', [
            'stream' => true,
        ]);

        expect($result)->toBeInstanceOf(\Psr\Http\Message\ResponseInterface::class)
            ->and($result->getBody()->getContents())->toBe('stream data');
    }

    public function test_it_throws_exception_on_invalid_json(): void
    {
        $this->mockHandler->append(new Response(200, [], 'invalid json'));

        expect(fn () => $this->client->get('/test'))
            ->toThrow(ConnectionException::class, 'Response is not a valid JSON');
    }

    public function test_it_throws_exception_on_get_request_failure(): void
    {
        $this->mockHandler->append(new Response(500, [], 'Server Error'));

        expect(fn () => $this->client->get('/test'))
            ->toThrow(ConnectionException::class, "GET request to '/test' failed");
    }

    public function test_it_throws_exception_on_post_request_failure(): void
    {
        $this->mockHandler->append(new Response(500, [], 'Server Error'));

        expect(fn () => $this->client->post('/test'))
            ->toThrow(ConnectionException::class, "POST request to '/test' failed");
    }
}
