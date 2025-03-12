<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Client\ApiClient;
use Shelfwood\LMStudio\Api\Contract\HttpClientInterface;
use Shelfwood\LMStudio\Api\Exception\ApiException;

beforeEach(function (): void {
    $this->httpClient = Mockery::mock(HttpClientInterface::class);
    $this->apiClient = new ApiClient($this->httpClient, 'http://example.com/api');
});

test('get sends get request', function (): void {
    // Set up the mock to expect a GET request
    $this->httpClient->shouldReceive('request')
        ->once()
        ->with('GET', 'http://example.com/api/endpoint', [], ['Content-Type' => 'application/json'])
        ->andReturn(['success' => true]);

    // Call the get method
    $response = $this->apiClient->get('/endpoint');

    // Assert the response is correct
    expect($response)->toBe(['success' => true]);
});

test('post sends post request', function (): void {
    // Set up the mock to expect a POST request
    $this->httpClient->shouldReceive('request')
        ->once()
        ->with('POST', 'http://example.com/api/endpoint', ['foo' => 'bar'], ['Content-Type' => 'application/json'])
        ->andReturn(['success' => true]);

    // Call the post method
    $response = $this->apiClient->post('/endpoint', ['foo' => 'bar']);

    // Assert the response is correct
    expect($response)->toBe(['success' => true]);
});

test('get with additional headers', function (): void {
    // Set up the mock to expect a GET request with additional headers
    $this->httpClient->shouldReceive('request')
        ->once()
        ->with('GET', 'http://example.com/api/endpoint', [], ['Content-Type' => 'application/json', 'Authorization' => 'Bearer token'])
        ->andReturn(['success' => true]);

    // Call the get method with additional headers
    $response = $this->apiClient->get('/endpoint', ['Authorization' => 'Bearer token']);

    // Assert the response is correct
    expect($response)->toBe(['success' => true]);
});

test('post with additional headers', function (): void {
    // Set up the mock to expect a POST request with additional headers
    $this->httpClient->shouldReceive('request')
        ->once()
        ->with('POST', 'http://example.com/api/endpoint', ['foo' => 'bar'], ['Content-Type' => 'application/json', 'Authorization' => 'Bearer token'])
        ->andReturn(['success' => true]);

    // Call the post method with additional headers
    $response = $this->apiClient->post('/endpoint', ['foo' => 'bar'], ['Authorization' => 'Bearer token']);

    // Assert the response is correct
    expect($response)->toBe(['success' => true]);
});

test('api exception is propagated', function (): void {
    // Set up the mock to throw an ApiException
    $this->httpClient->shouldReceive('request')
        ->once()
        ->andThrow(new ApiException('API Error'));

    // Expect an ApiException to be thrown
    expect(fn () => $this->apiClient->get('/endpoint'))
        ->toThrow(ApiException::class, 'API Error');
});
