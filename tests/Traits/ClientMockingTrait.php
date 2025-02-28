<?php

declare(strict_types=1);

namespace Tests\Traits;

use Mockery;
use Mockery\MockInterface;
use Shelfwood\LMStudio\Http\Client;
use Shelfwood\LMStudio\Http\StreamingResponseHandler;
use Shelfwood\LMStudio\LMS;
use Shelfwood\LMStudio\OpenAI;

trait ClientMockingTrait
{
    /**
     * Create a mock HTTP client.
     *
     * @param  array  $methods  Methods to mock with their return values
     * @return MockInterface&Client
     */
    protected function createMockHttpClient(array $methods = []): MockInterface
    {
        $mock = Mockery::mock(Client::class);

        foreach ($methods as $method => $returnValue) {
            $mock->shouldReceive($method)
                ->once()
                ->andReturn($returnValue);
        }

        return $mock;
    }

    /**
     * Create a mock streaming handler.
     *
     * @param  array  $methods  Methods to mock with their return values
     * @return MockInterface&StreamingResponseHandler
     */
    protected function createMockStreamingHandler(array $methods = []): MockInterface
    {
        $mock = Mockery::mock(StreamingResponseHandler::class);

        foreach ($methods as $method => $returnValue) {
            $mock->shouldReceive($method)
                ->once()
                ->andReturn($returnValue);
        }

        return $mock;
    }

    /**
     * Create a mock LMS client.
     *
     * @param  array  $methods  Methods to mock with their return values
     * @return MockInterface&LMS
     */
    protected function createMockLMS(array $methods = []): MockInterface
    {
        $mock = Mockery::mock(LMS::class);

        foreach ($methods as $method => $returnValue) {
            $mock->shouldReceive($method)
                ->once()
                ->andReturn($returnValue);
        }

        return $mock;
    }

    /**
     * Create a mock OpenAI client.
     *
     * @param  array  $methods  Methods to mock with their return values
     * @return MockInterface&OpenAI
     */
    protected function createMockOpenAI(array $methods = []): MockInterface
    {
        $mock = Mockery::mock(OpenAI::class);

        foreach ($methods as $method => $returnValue) {
            $mock->shouldReceive($method)
                ->once()
                ->andReturn($returnValue);
        }

        return $mock;
    }
}
