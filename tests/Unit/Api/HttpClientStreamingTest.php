<?php

declare(strict_types=1);

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery as m;
use Psr\Log\NullLogger;

// Use Mockery for mocking
use Shelfwood\LMStudio\Api\Client\HttpClient;
use Shelfwood\LMStudio\Api\Exception\ApiException;

describe('HttpClientStreaming', function (): void {
    // Use MockeryPHPUnitIntegration trait if using PHPUnit
    // Otherwise, ensure Mockery::close() is called in tearDown

    beforeEach(function (): void {
        // Create a partial mock, specifically mocking the _curl* methods
        // AND passing arguments to the original constructor
        $this->httpClient = m::mock(
            HttpClient::class.'[_curlInit, _curlSetoptArray, _curlExec, _curlError, _curlErrno, _curlGetInfo, _curlClose]',
            [new NullLogger] // Arguments for the original __construct
        );
        $this->httpClient->shouldAllowMockingProtectedMethods(); // Allow mocking protected methods
    });

    afterEach(function (): void {
        m::close(); // Close Mockery after each test
    });

    test('requestStream handles streaming data correctly', function (): void {
        // Mock the specific protected methods for this scenario
        $this->httpClient->shouldReceive('_curlInit')->once()->andReturn(curl_init()); // Return a real (but unused) handle
        $this->httpClient->shouldReceive('_curlSetoptArray')->once()->with(m::type(CurlHandle::class), m::type('array'))->andReturn(true);
        $this->httpClient->shouldReceive('_curlExec')->once()->with(m::type(CurlHandle::class))->andReturn(true);
        $this->httpClient->shouldReceive('_curlError')->once()->with(m::type(CurlHandle::class))->andReturn('');
        $this->httpClient->shouldReceive('_curlErrno')->once()->with(m::type(CurlHandle::class))->andReturn(0);
        $this->httpClient->shouldReceive('_curlGetInfo')->once()->with(m::type(CurlHandle::class), CURLINFO_HTTP_CODE)->andReturn(200);
        $this->httpClient->shouldReceive('_curlClose')->once()->with(m::type(CurlHandle::class));

        // Create a dummy callback
        $callback = function ($chunk): void {};

        // Call the actual requestStream method on the partial mock
        $this->httpClient->requestStream(
            'POST',
            'http://example.com/api',
            ['model' => 'test-model', 'messages' => []],
            $callback
        );

        // No exception expected, assertion passes if mocks are met
        expect(true)->toBeTrue();
    });

    test('requestStream throws exception on curl error', function (): void {
        // Mock the specific protected methods for this scenario
        $this->httpClient->shouldReceive('_curlInit')->once()->andReturn(curl_init());
        $this->httpClient->shouldReceive('_curlSetoptArray')->once()->with(m::type(CurlHandle::class), m::type('array'))->andReturn(true);
        $this->httpClient->shouldReceive('_curlExec')->once()->with(m::type(CurlHandle::class))->andReturn(false); // Simulate exec failure
        $this->httpClient->shouldReceive('_curlError')->once()->with(m::type(CurlHandle::class))->andReturn('Connection refused');
        $this->httpClient->shouldReceive('_curlErrno')->once()->with(m::type(CurlHandle::class))->andReturn(CURLE_COULDNT_CONNECT);
        $this->httpClient->shouldReceive('_curlGetInfo')->once()->with(m::type(CurlHandle::class), CURLINFO_HTTP_CODE)->andReturn(0);
        $this->httpClient->shouldReceive('_curlClose')->once()->with(m::type(CurlHandle::class));

        $callback = fn ($chunk) => null;

        // Expect the correct exception
        expect(fn () => $this->httpClient->requestStream(
            'POST',
            'http://example.com/api',
            ['model' => 'test-model', 'messages' => []],
            $callback
        ))->toThrow(ApiException::class, 'cURL Error: Connection refused');
    });

    test('requestStream throws exception on api error', function (): void {
        // Mock the specific protected methods for this scenario
        $this->httpClient->shouldReceive('_curlInit')->once()->andReturn(curl_init());
        $this->httpClient->shouldReceive('_curlSetoptArray')->once()->with(m::type(CurlHandle::class), m::type('array'))->andReturn(true);
        $this->httpClient->shouldReceive('_curlExec')->once()->with(m::type(CurlHandle::class))->andReturn(true);
        $this->httpClient->shouldReceive('_curlError')->once()->with(m::type(CurlHandle::class))->andReturn('');
        $this->httpClient->shouldReceive('_curlErrno')->once()->with(m::type(CurlHandle::class))->andReturn(0);
        $this->httpClient->shouldReceive('_curlGetInfo')->once()->with(m::type(CurlHandle::class), CURLINFO_HTTP_CODE)->andReturn(401); // Simulate API error
        $this->httpClient->shouldReceive('_curlClose')->once()->with(m::type(CurlHandle::class));

        $callback = fn ($chunk) => null;

        // Expect the correct exception
        expect(fn () => $this->httpClient->requestStream(
            'POST',
            'http://example.com/api',
            ['model' => 'test-model', 'messages' => []],
            $callback
        ))->toThrow(ApiException::class, 'API Error: HTTP status code 401');
    });
});
