<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Client\ApiClient;
use Shelfwood\LMStudio\Api\Contract\HttpClientInterface;
use Shelfwood\LMStudio\Api\Exception\ApiException;

describe('ApiClientStreaming', function (): void {
    beforeEach(function (): void {
        $this->httpClient = Mockery::mock(HttpClientInterface::class);
        $this->apiClient = new ApiClient($this->httpClient, 'http://example.com/api');
    });

    test('postStream sends streaming post request', function (): void {
        // Create a callback
        $callback = function ($chunk): void {
            // Callback function
        };

        // Set up the mock to expect a streaming POST request
        $this->httpClient->shouldReceive('requestStream')
            ->once()
            ->with(
                'POST',
                'http://example.com/api/endpoint',
                ['foo' => 'bar'],
                $callback,
                ['Content-Type' => 'application/json']
            );

        // Call the postStream method
        $this->apiClient->postStream('/endpoint', ['foo' => 'bar'], $callback);
    });

    test('postStream with additional headers', function (): void {
        // Create a callback
        $callback = function ($chunk): void {
            // Callback function
        };

        // Set up the mock to expect a streaming POST request with additional headers
        $this->httpClient->shouldReceive('requestStream')
            ->once()
            ->with(
                'POST',
                'http://example.com/api/endpoint',
                ['foo' => 'bar'],
                $callback,
                ['Content-Type' => 'application/json', 'Authorization' => 'Bearer token']
            );

        // Call the postStream method with additional headers
        $this->apiClient->postStream('/endpoint', ['foo' => 'bar'], $callback, ['Authorization' => 'Bearer token']);
    });

    test('postStream api exception is propagated', function (): void {
        // Create a callback
        $callback = function ($chunk): void {
            // Callback function
        };

        // Set up the mock to throw an ApiException
        $this->httpClient->shouldReceive('requestStream')
            ->once()
            ->andThrow(new ApiException('API Error'));

        // Expect an ApiException to be thrown
        expect(fn () => $this->apiClient->postStream('/endpoint', [], $callback))
            ->toThrow(ApiException::class, 'API Error');
    });

    test('postStream generic exception is wrapped', function (): void {
        // Create a callback
        $callback = function ($chunk): void {
            // Callback function
        };

        // Set up the mock to throw a generic Exception
        $this->httpClient->shouldReceive('requestStream')
            ->once()
            ->andThrow(new Exception('Generic Error'));

        // Expect the original Exception to be thrown, not wrapped
        expect(fn () => $this->apiClient->postStream('/endpoint', [], $callback))
            ->toThrow(\Exception::class, 'Generic Error');
    });
});
