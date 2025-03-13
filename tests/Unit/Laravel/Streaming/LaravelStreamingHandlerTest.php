<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Laravel\Streaming\LaravelStreamingHandler;
use Symfony\Component\HttpFoundation\StreamedResponse;

describe('LaravelStreamingHandler', function (): void {
    beforeEach(function (): void {
        $this->handler = new LaravelStreamingHandler;
    });

    test('emit server sent events sets the flag correctly', function (): void {
        $this->handler->emitServerSentEvents(true);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($this->handler);
        $property = $reflection->getProperty('emitServerSentEvents');
        $property->setAccessible(true);

        expect($property->getValue($this->handler))->toBeTrue();
    });

    test('set event name sets the event name correctly', function (): void {
        $this->handler->setEventName('custom-event');

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($this->handler);
        $property = $reflection->getProperty('eventName');
        $property->setAccessible(true);

        expect($property->getValue($this->handler))->toBe('custom-event');
    });

    test('set additional event data sets the additional data correctly', function (): void {
        $data = ['key' => 'value'];
        $this->handler->setAdditionalEventData($data);

        // Use reflection to access protected property
        $reflection = new \ReflectionClass($this->handler);
        $property = $reflection->getProperty('additionalEventData');
        $property->setAccessible(true);

        expect($property->getValue($this->handler))->toBe($data);
    });

    test('to streamed response returns a streamed response', function (): void {
        $response = $this->handler->toStreamedResponse();

        expect($response)->toBeInstanceOf(StreamedResponse::class);
        expect($response->getStatusCode())->toBe(200);

        $headers = $response->headers->all();
        expect($headers['content-type'][0])->toBe('text/plain');
        expect($headers['cache-control'][0])->toBe('no-cache, private');
        expect($headers['connection'][0])->toBe('keep-alive');
        expect($headers['x-accel-buffering'][0])->toBe('no');
    });

    test('to streamed response with sse returns a streamed response with correct headers', function (): void {
        $this->handler->emitServerSentEvents(true);
        $response = $this->handler->toStreamedResponse();

        expect($response)->toBeInstanceOf(StreamedResponse::class);
        expect($response->getStatusCode())->toBe(200);

        $headers = $response->headers->all();
        expect($headers['content-type'][0])->toBe('text/event-stream');
        expect($headers['cache-control'][0])->toBe('no-cache, private');
        expect($headers['connection'][0])->toBe('keep-alive');
        expect($headers['x-accel-buffering'][0])->toBe('no');
    });
});
