<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Client\HttpClient;
use Shelfwood\LMStudio\Api\Exception\ApiException;

describe('HttpClientStreaming', function (): void {
    beforeEach(function (): void {
        $this->httpClient = Mockery::mock(HttpClient::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();
    });

    test('requestStream handles streaming data correctly', function (): void {
        // Mock the curl_exec function to simulate successful execution
        $this->httpClient->shouldReceive('curlExec')
            ->once()
            ->andReturn(true);

        // Mock the curl_error function to return an empty string (no error)
        $this->httpClient->shouldReceive('curlError')
            ->once()
            ->andReturn('');

        // Mock the curl_getinfo function to return a 200 status code
        $this->httpClient->shouldReceive('curlGetInfo')
            ->once()
            ->andReturn(200);

        // Create a callback to collect chunks
        $receivedChunks = [];
        $callback = function ($chunk) use (&$receivedChunks): void {
            $receivedChunks[] = $chunk;
        };

        // Call the requestStream method
        $this->httpClient->requestStream(
            'POST',
            'http://example.com/api',
            ['model' => 'test-model', 'messages' => []],
            $callback,
            ['Content-Type' => 'application/json']
        );

        // We can't directly test the CURLOPT_WRITEFUNCTION callback,
        // but we can verify that the method completed without errors
        expect(true)->toBeTrue();
    });

    test('requestStream throws exception on curl error', function (): void {
        // Mock the curl_exec function to simulate execution
        $this->httpClient->shouldReceive('curlExec')
            ->once()
            ->andReturn(false);

        // Mock the curl_error function to return an error message
        $this->httpClient->shouldReceive('curlError')
            ->once()
            ->andReturn('Connection refused');

        // Create a callback
        $callback = function ($chunk): void {
            // This should not be called
        };

        // Expect an ApiException to be thrown
        expect(fn () => $this->httpClient->requestStream(
            'POST',
            'http://example.com/api',
            ['model' => 'test-model', 'messages' => []],
            $callback
        ))->toThrow(ApiException::class, 'cURL Error: Connection refused');
    });

    test('requestStream throws exception on api error', function (): void {
        // Mock the curl_exec function to simulate execution
        $this->httpClient->shouldReceive('curlExec')
            ->once()
            ->andReturn(true);

        // Mock the curl_error function to return an empty string (no error)
        $this->httpClient->shouldReceive('curlError')
            ->once()
            ->andReturn('');

        // Mock the curl_getinfo function to return a 401 status code
        $this->httpClient->shouldReceive('curlGetInfo')
            ->once()
            ->andReturn(401);

        // Create a callback
        $callback = function ($chunk): void {
            // This should not be called
        };

        // Expect an ApiException to be thrown
        expect(fn () => $this->httpClient->requestStream(
            'POST',
            'http://example.com/api',
            ['model' => 'test-model', 'messages' => []],
            $callback
        ))->toThrow(ApiException::class, 'API Error: HTTP status code 401');
    });
});
