<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Exceptions\LMStudioException;
use Shelfwood\LMStudio\Http\Client;

beforeEach(function (): void {
    $this->config = new LMStudioConfig(
        baseUrl: 'http://example.com',
        apiKey: 'test-key'
    );
});

test('client makes successful GET request', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode(['data' => 'test'])),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $guzzle = new GuzzleClient(['handler' => $handlerStack]);

    $client = new class($this->config, $guzzle) extends Client
    {
        public function __construct($config, $guzzle)
        {
            parent::__construct($config);
            $this->client = $guzzle;
        }
    };

    $response = $client->get('test');
    expect($response)->toBe(['data' => 'test']);
});

test('client makes successful POST request', function (): void {
    $mock = new MockHandler([
        new Response(200, [], json_encode(['data' => 'test'])),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $guzzle = new GuzzleClient(['handler' => $handlerStack]);

    $client = new class($this->config, $guzzle) extends Client
    {
        public function __construct($config, $guzzle)
        {
            parent::__construct($config);
            $this->client = $guzzle;
        }
    };

    $response = $client->post('test', ['key' => 'value']);
    expect($response)->toBe(['data' => 'test']);
});

test('client handles streaming responses', function (): void {
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, implode("\n\n", [
        'data: '.json_encode(['chunk' => 1]),
        'data: '.json_encode(['chunk' => 2]),
        'data: [DONE]',
    ]));
    rewind($stream);

    $mock = new MockHandler([
        new Response(200, [], new Stream($stream)),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $guzzle = new GuzzleClient(['handler' => $handlerStack]);

    $client = new class($this->config, $guzzle) extends Client
    {
        public function __construct($config, $guzzle)
        {
            parent::__construct($config);
            $this->client = $guzzle;
        }
    };

    $chunks = iterator_to_array($client->stream('test'));
    expect($chunks)->toBe([
        ['chunk' => 1],
        ['chunk' => 2],
    ]);
});

test('client throws exception on request error', function (): void {
    $mock = new MockHandler([
        new Response(500, [], json_encode(['error' => 'test'])),
    ]);

    $handlerStack = HandlerStack::create($mock);
    $guzzle = new GuzzleClient(['handler' => $handlerStack]);

    $client = new class($this->config, $guzzle) extends Client
    {
        public function __construct($config, $guzzle)
        {
            parent::__construct($config);
            $this->client = $guzzle;
        }
    };

    expect(fn () => $client->get('test'))->toThrow(LMStudioException::class);
});
