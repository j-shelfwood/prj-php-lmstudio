<?php

declare(strict_types=1);

use Mockery as m;
use Psr\Log\NullLogger;
use Shelfwood\LMStudio\Api\Client\HttpClient;
use Shelfwood\LMStudio\Api\Exception\ApiException;

describe('HttpClient', function (): void {
    beforeEach(function (): void {
        // Create a partial mock, allowing protected methods to be mocked
        $this->httpClient = m::mock(HttpClient::class.'[_curlInit,_curlSetoptArray,_curlExec,_curlGetInfo,_curlError,_curlErrno,_curlClose]', [new NullLogger])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();
    });

    afterEach(function (): void {
        m::close();
    });

    test('request sends correct data', function (): void {
        // Mock protected curl methods
        $this->httpClient->shouldReceive('_curlInit')->once()->andReturn(curl_init());
        $this->httpClient->shouldReceive('_curlSetoptArray')->once()->with(m::type(CurlHandle::class), m::type('array'))->andReturn(true);
        $this->httpClient->shouldReceive('_curlExec')->once()->with(m::type(CurlHandle::class))->andReturn(json_encode([
            'success' => true,
            'data' => ['message' => 'Hello, world!'],
        ]));
        $this->httpClient->shouldReceive('_curlGetInfo')->once()->with(m::type(CurlHandle::class), CURLINFO_HTTP_CODE)->andReturn(200);
        $this->httpClient->shouldReceive('_curlError')->once()->with(m::type(CurlHandle::class))->andReturn('');
        $this->httpClient->shouldReceive('_curlErrno')->never();
        $this->httpClient->shouldReceive('_curlClose')->once()->with(m::type(CurlHandle::class));

        // Call the request method
        $response = $this->httpClient->request('GET', 'http://example.com/api', ['foo' => 'bar'], ['Content-Type' => 'application/json']);

        // Assert the response is correct
        expect($response)->toBe([
            'success' => true,
            'data' => ['message' => 'Hello, world!'],
        ]);
    });

    test('request throws exception on curl error', function (): void {
        // Mock protected curl methods
        $this->httpClient->shouldReceive('_curlInit')->once()->andReturn(curl_init());
        $this->httpClient->shouldReceive('_curlSetoptArray')->once()->with(m::type(CurlHandle::class), m::type('array'))->andReturn(true);
        $this->httpClient->shouldReceive('_curlExec')->once()->with(m::type(CurlHandle::class))->andReturn(false); // Simulate exec failure
        $this->httpClient->shouldReceive('_curlGetInfo')->once()->with(m::type(CurlHandle::class), CURLINFO_HTTP_CODE)->andReturn(0); // Status code is usually 0 on connection errors
        $this->httpClient->shouldReceive('_curlError')->once()->with(m::type(CurlHandle::class))->andReturn('Connection refused');
        $this->httpClient->shouldReceive('_curlErrno')->never(); // Not called because _curlError is checked first
        $this->httpClient->shouldReceive('_curlClose')->once()->with(m::type(CurlHandle::class));

        // Expect an ApiException to be thrown
        expect(fn () => $this->httpClient->request('GET', 'http://example.com/api'))
            ->toThrow(ApiException::class, 'cURL Error: Connection refused');
    });

    test('request throws exception on api error', function (): void {
        // Mock protected curl methods
        $this->httpClient->shouldReceive('_curlInit')->once()->andReturn(curl_init());
        $this->httpClient->shouldReceive('_curlSetoptArray')->once()->with(m::type(CurlHandle::class), m::type('array'))->andReturn(true);
        $this->httpClient->shouldReceive('_curlExec')->once()->with(m::type(CurlHandle::class))->andReturn(json_encode([
            'error' => [
                'message' => 'Invalid API key',
            ],
        ]));
        $this->httpClient->shouldReceive('_curlGetInfo')->once()->with(m::type(CurlHandle::class), CURLINFO_HTTP_CODE)->andReturn(401); // Simulate API error status
        $this->httpClient->shouldReceive('_curlError')->once()->with(m::type(CurlHandle::class))->andReturn('');
        $this->httpClient->shouldReceive('_curlErrno')->never();
        $this->httpClient->shouldReceive('_curlClose')->once()->with(m::type(CurlHandle::class));

        // Expect an ApiException to be thrown
        expect(fn () => $this->httpClient->request('GET', 'http://example.com/api'))
            ->toThrow(ApiException::class, 'API Error: Invalid API key'); // Check the error message extraction logic if needed
    });

    test('request throws exception on invalid json', function (): void {
        // Mock protected curl methods
        $this->httpClient->shouldReceive('_curlInit')->once()->andReturn(curl_init());
        $this->httpClient->shouldReceive('_curlSetoptArray')->once()->with(m::type(CurlHandle::class), m::type('array'))->andReturn(true);
        $this->httpClient->shouldReceive('_curlExec')->once()->with(m::type(CurlHandle::class))->andReturn('{"invalid": json}'); // Invalid JSON
        $this->httpClient->shouldReceive('_curlGetInfo')->once()->with(m::type(CurlHandle::class), CURLINFO_HTTP_CODE)->andReturn(200);
        $this->httpClient->shouldReceive('_curlError')->once()->with(m::type(CurlHandle::class))->andReturn('');
        $this->httpClient->shouldReceive('_curlErrno')->never();
        $this->httpClient->shouldReceive('_curlClose')->once()->with(m::type(CurlHandle::class));

        // Expect an ApiException to be thrown for invalid JSON
        expect(fn () => $this->httpClient->request('GET', 'http://example.com/api'))
            ->toThrow(ApiException::class, 'Invalid JSON response: Syntax error'); // More specific JSON error
    });
});
