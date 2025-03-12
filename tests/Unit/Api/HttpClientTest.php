<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Client\HttpClient;
use Shelfwood\LMStudio\Api\Exception\ApiException;

beforeEach(function (): void {
    $this->httpClient = Mockery::mock(HttpClient::class)
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();
});

test('request sends correct data', function (): void {
    // Mock the curl_exec function to return a successful response
    $this->httpClient->shouldReceive('curlExec')->andReturn(json_encode([
        'success' => true,
        'data' => ['message' => 'Hello, world!'],
    ]));

    // Mock the curl_getinfo function to return a 200 status code
    $this->httpClient->shouldReceive('curlGetInfo')->andReturn(200);

    // Mock the curl_error function to return an empty string (no error)
    $this->httpClient->shouldReceive('curlError')->andReturn('');

    // Call the request method
    $response = $this->httpClient->request('GET', 'http://example.com/api', ['foo' => 'bar'], ['Content-Type' => 'application/json']);

    // Assert the response is correct
    expect($response)->toBe([
        'success' => true,
        'data' => ['message' => 'Hello, world!'],
    ]);
});

test('request throws exception on curl error', function (): void {
    // Mock the curl_exec function to return false (error)
    $this->httpClient->shouldReceive('curlExec')->andReturn(false);

    // Mock the curl_error function to return an error message
    $this->httpClient->shouldReceive('curlError')->andReturn('Connection refused');

    // Expect an ApiException to be thrown
    expect(fn () => $this->httpClient->request('GET', 'http://example.com/api'))
        ->toThrow(ApiException::class, 'cURL Error: Connection refused');
});

test('request throws exception on api error', function (): void {
    // Mock the curl_exec function to return an error response
    $this->httpClient->shouldReceive('curlExec')->andReturn(json_encode([
        'error' => [
            'message' => 'Invalid API key',
        ],
    ]));

    // Mock the curl_getinfo function to return a 401 status code
    $this->httpClient->shouldReceive('curlGetInfo')->andReturn(401);

    // Mock the curl_error function to return an empty string (no error)
    $this->httpClient->shouldReceive('curlError')->andReturn('');

    // Expect an ApiException to be thrown
    expect(fn () => $this->httpClient->request('GET', 'http://example.com/api'))
        ->toThrow(ApiException::class, 'API Error: Invalid API key');
});

test('request throws exception on invalid json', function (): void {
    // Mock the curl_exec function to return invalid JSON
    $this->httpClient->shouldReceive('curlExec')->andReturn('{"invalid": json}');

    // Mock the curl_getinfo function to return a 200 status code
    $this->httpClient->shouldReceive('curlGetInfo')->andReturn(200);

    // Mock the curl_error function to return an empty string (no error)
    $this->httpClient->shouldReceive('curlError')->andReturn('');

    // Expect an ApiException to be thrown
    expect(fn () => $this->httpClient->request('GET', 'http://example.com/api'))
        ->toThrow(ApiException::class, 'Invalid JSON response:');
});
