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

class Client
{
    protected GuzzleClient $client;

    protected DebugLogger $logger;

    public function __construct(
        private LMStudioConfig $config,
        ?LoggerInterface $logger = null
    ) {
        // Create a handler stack with logging middleware if debug is enabled
        $stack = HandlerStack::create();

        // Initialize the debug logger
        $debugConfig = $config->getDebugConfig();
        $this->logger = new DebugLogger(
            $debugConfig['enabled'] ?? (bool) getenv('LMSTUDIO_DEBUG'),
            $debugConfig['verbose'] ?? false,
            $debugConfig['log_file'] ?? null,
            $logger
        );

        if ($this->logger->isEnabled()) {
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
                    $response->getStatusCode(),
                    ['headers' => $response->getHeaders()]
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
     * Set the debug logger instance.
     */
    public function setLogger(DebugLogger $logger): self
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
     * @return \Generator A generator yielding decoded chunks
     *
     * @throws StreamingException If the request fails
     */
    public function stream(string $uri, array $data = []): \Generator
    {
        $attempts = 0;
        $maxAttempts = $this->config->getMaxRetries() ?? 3;
        $backoffStrategy = [1, 2, 5]; // seconds
        $startTime = microtime(true);
        $chunkCount = 0;
        $lastChunk = null;

        while ($attempts < $maxAttempts) {
            try {
                $this->logger->logRequest('POST', $uri, $data);
                $this->logger->log('Streaming request (attempt '.($attempts + 1).')', [
                    'uri' => $uri,
                    'max_attempts' => $maxAttempts,
                ]);

                $response = $this->client->post($uri, [
                    'json' => $data,
                    'stream' => true,
                    'timeout' => $this->config->getTimeout(),
                    'connect_timeout' => $this->config->getConnectTimeout() ?? 10,
                ]);

                $this->logger->logResponse($response->getStatusCode(), []);

                $buffer = '';
                $stream = $response->getBody();
                $lastActivityTime = microtime(true);
                $idleTimeout = $this->config->getIdleTimeout() ?? 15; // seconds

                while (! $stream->eof()) {
                    $chunk = $stream->read(1024);

                    if (! empty($chunk)) {
                        $lastActivityTime = microtime(true);
                        $buffer .= $chunk;

                        // Process complete SSE messages
                        while (($pos = strpos($buffer, "\n\n")) !== false) {
                            $message = substr($buffer, 0, $pos);
                            $buffer = substr($buffer, $pos + 2);

                            foreach (explode("\n", $message) as $line) {
                                if (str_starts_with($line, 'data: ')) {
                                    $data = substr($line, 6);

                                    if ($data === '[DONE]') {
                                        $duration = round(microtime(true) - $startTime, 2);
                                        $this->logger->log('Streaming completed with [DONE]', [
                                            'duration' => $duration,
                                            'chunks' => $chunkCount,
                                        ]);

                                        return;
                                    }

                                    $decoded = json_decode($data, true);

                                    if ($decoded !== null) {
                                        $chunkCount++;
                                        $lastChunk = $decoded;

                                        // Log every 10th chunk to avoid excessive logging
                                        if ($chunkCount % 10 === 0) {
                                            $this->logger->log('Received chunks', [
                                                'count' => $chunkCount,
                                            ]);
                                        }

                                        $this->logger->logStreamingChunk($decoded, $chunkCount);

                                        yield $decoded;
                                    }
                                }
                            }
                        }
                    } else {
                        // Check for idle timeout
                        if (microtime(true) - $lastActivityTime > $idleTimeout) {
                            $this->logger->log('Stream idle timeout reached', [
                                'idle_timeout' => $idleTimeout,
                            ]);

                            break;
                        }

                        // Small sleep to prevent CPU spinning
                        usleep(50000); // 50ms
                    }

                    // Check for overall timeout
                    if (microtime(true) - $startTime > $this->config->getTimeout()) {
                        $this->logger->log('Stream overall timeout reached', [
                            'timeout' => $this->config->getTimeout(),
                        ]);

                        break;
                    }
                }

                // If we got here without an exception, we're done
                return;
            } catch (\Exception $e) {
                $attempts++;
                $elapsedTime = microtime(true) - $startTime;

                $this->logger->logError('Streaming error', $e);
                $this->logger->log('Streaming error details', [
                    'elapsed_time' => $elapsedTime,
                    'chunks_received' => $chunkCount,
                    'attempt' => $attempts,
                ]);

                if ($attempts >= $maxAttempts) {
                    throw new StreamingException(
                        "Streaming failed after {$attempts} attempts: ".$e->getMessage(),
                        $e->getCode(),
                        $e,
                        $lastChunk !== null ? (string) json_encode($lastChunk) : null,
                        $chunkCount,
                        $elapsedTime
                    );
                }

                $backoffTime = $backoffStrategy[min($attempts - 1, count($backoffStrategy) - 1)];

                $this->logger->log('Retrying streaming request', [
                    'backoff_time' => $backoffTime,
                    'attempt' => $attempts,
                    'max_attempts' => $maxAttempts,
                ]);

                sleep($backoffTime);
            }
        }
    }

    /**
     * Make a request.
     *
     * @throws LMStudioException
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        try {
            $this->logger->logRequest($method, $uri, $options);

            $response = $this->client->request($method, $uri, $options);
            $contents = $response->getBody()->getContents();

            $this->logger->logResponse($response->getStatusCode(), $contents);

            return json_decode($contents, true);
        } catch (GuzzleException $e) {
            $this->logger->logError('Request error', $e);

            throw new LMStudioException($e->getMessage(), $e->getCode(), $e);
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
