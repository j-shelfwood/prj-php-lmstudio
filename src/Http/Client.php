<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Exceptions\LMStudioException;
use Shelfwood\LMStudio\Exceptions\StreamingException;
use Shelfwood\LMStudio\Logging\Logger;

class Client
{
    protected GuzzleClient $client;

    protected Logger $logger;

    public function __construct(
        private LMStudioConfig $config,
        ?LoggerInterface $psr3Logger = null
    ) {
        // Create a handler stack with logging middleware if debug is enabled
        $stack = HandlerStack::create();

        // Get the logger from config
        $this->logger = $config->getLogger();

        if ($this->logger) {
            // Add request logging
            $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
                $this->logger->logRequest(
                    $request->getMethod(),
                    (string) $request->getUri(),
                    ['headers' => $request->getHeaders()]
                );

                return $request;
            }));

            // Add response logging
            $stack->push(Middleware::mapResponse(function (ResponseInterface $response) {
                $this->logger->logResponse(
                    (string) $response->getBody(),
                    $response
                );

                return $response;
            }));
        }

        $this->client = new GuzzleClient([
            'base_uri' => $config->getBaseUrl(),
            'timeout' => $config->getTimeout(),
            'headers' => array_merge(
                [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                $config->getApiKey() ? ['Authorization' => 'Bearer '.$config->getApiKey()] : [],
                $config->getHeaders()
            ),
            'handler' => $stack,
        ]);
    }

    /**
     * Set the Guzzle client instance.
     */
    public function setGuzzleClient(GuzzleClient $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Set the logger instance.
     */
    public function setLogger(Logger $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Make a GET request.
     *
     * @param  array<string, mixed>  $query
     *
     * @throws LMStudioException
     */
    public function get(string $uri, array $query = []): array
    {
        return $this->request('GET', $uri, ['query' => $query]);
    }

    /**
     * Make a POST request.
     *
     * @throws LMStudioException
     */
    public function post(string $uri, array $data = []): array
    {
        return $this->request('POST', $uri, ['json' => $data]);
    }

    /**
     * Send a streaming request to the API.
     *
     * @param  string  $uri  The URI to send the request to
     * @param  array  $data  The data to send with the request
     * @return \Generator A generator yielding StreamChunk objects
     *
     * @throws StreamingException If the request fails
     */
    public function stream(string $uri, array $data = []): \Generator
    {
        $options = [
            'json' => $data,
            'stream' => true,
            'connect_timeout' => $this->config->getConnectTimeout(),
            'read_timeout' => $this->config->getIdleTimeout(),
        ];

        $maxRetries = $this->config->getMaxRetries() ?? 3;
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            $attempt++;

            try {
                // Log the streaming request
                $this->logger->logRequest('POST', $uri, [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'data' => $data,
                ]);

                $startTime = microtime(true);
                $response = $this->client->request('POST', $uri, array_merge($options, [
                    'stream' => true,
                ]));
                $endTime = microtime(true);

                // Log successful connection
                $this->logger->log("Stream connected successfully (attempt {$attempt}/{$maxRetries})", [
                    'status' => $response->getStatusCode(),
                    'headers' => $response->getHeaders(),
                    'time' => $endTime - $startTime,
                ]);

                $handler = new StreamingResponseHandler($response->getBody());
                $streamTime = microtime(true);

                // Use the handler to process the stream and yield StreamChunk objects
                foreach ($handler->stream() as $rawChunk) {
                    $chunk = new \Shelfwood\LMStudio\ValueObjects\StreamChunk($rawChunk);

                    // Log tool calls if present
                    if ($chunk->hasToolCalls()) {
                        $this->logger->log('Stream chunk contains tool calls', [
                            'tool_calls' => $chunk->getToolCalls(),
                        ]);
                    }

                    yield $chunk;
                }

                // Log successful completion
                $this->logger->log('Stream completed successfully', [
                    'time' => microtime(true) - $streamTime,
                    'total_time' => microtime(true) - $startTime,
                ]);

                return;
            } catch (GuzzleException $e) {
                $lastException = $e;

                // Log the error with detailed information
                $this->logger->logError($uri, $e, [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'data' => $data,
                    'options' => $options,
                ]);

                if ($attempt < $maxRetries) {
                    // Wait before retrying (exponential backoff)
                    $sleepTime = min(pow(2, $attempt - 1) * 0.5, 5);
                    $this->logger->log("Retrying in {$sleepTime} seconds (attempt {$attempt}/{$maxRetries})");
                    sleep((int) $sleepTime);
                }
            }
        }

        // If we get here, all retries failed
        $message = "Streaming failed after {$maxRetries} attempts";

        if ($lastException) {
            $message .= ': '.$lastException->getMessage();
        }

        throw new StreamingException($message, 0, $lastException);
    }

    /**
     * Make a request.
     *
     * @throws LMStudioException
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        try {
            $startTime = microtime(true);
            $response = $this->client->request($method, $uri, $options);
            $duration = microtime(true) - $startTime;

            $contents = (string) $response->getBody();
            $decoded = json_decode($contents, true);

            // Log the successful response
            $this->logger->logResponse($uri, $decoded, $duration);

            return $decoded;
        } catch (GuzzleException $e) {
            // Log the error with detailed information
            $this->logger->logError($uri, $e, [
                'method' => $method,
                'options' => $options,
            ]);

            throw new LMStudioException('API request failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Check the health of the LMStudio server connection.
     *
     * @return bool True if the connection is healthy, false otherwise
     */
    public function checkHealth(): bool
    {
        try {
            $this->logger->log('Performing health check', [
                'base_url' => $this->config->getBaseUrl(),
            ]);

            // Try to get models as a simple health check
            $response = $this->client->get('models', [
                'timeout' => $this->config->getConnectTimeout() ?? 5,
            ]);

            $healthy = $response->getStatusCode() === 200;

            $this->logger->log('Health check '.($healthy ? 'succeeded' : 'failed'), [
                'status_code' => $response->getStatusCode(),
            ]);

            return $healthy;
        } catch (\Exception $e) {
            $this->logger->logError('Health check failed', $e);

            return false;
        }
    }
}
