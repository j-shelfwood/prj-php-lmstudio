<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Exceptions\LMStudioException;

class Client
{
    protected GuzzleClient $client;

    protected bool $debug = false;

    public function __construct(
        private LMStudioConfig $config
    ) {
        // Create a handler stack with logging middleware if debug is enabled
        $stack = HandlerStack::create();

        // Check if debug is enabled via environment variable
        $this->debug = (bool) getenv('LMSTUDIO_DEBUG');

        if ($this->debug) {
            // Add request logging
            $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
                echo "\n[DEBUG] Request: ".$request->getMethod().' '.$request->getUri()."\n";
                echo '[DEBUG] Headers: '.json_encode($request->getHeaders())."\n";

                return $request;
            }));

            // Add response logging
            $stack->push(Middleware::mapResponse(function (ResponseInterface $response) {
                echo '[DEBUG] Response Status: '.$response->getStatusCode()."\n";
                echo '[DEBUG] Response Headers: '.json_encode($response->getHeaders())."\n";

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
     * Make a streaming POST request.
     *
     * @throws LMStudioException
     */
    public function stream(string $uri, array $data = []): \Generator
    {
        try {
            if ($this->debug) {
                echo "\n[DEBUG] Streaming Request: POST ".$this->config->getBaseUrl().'/'.$uri."\n";
                echo '[DEBUG] Streaming Data: '.json_encode($data)."\n";
            }

            $response = $this->client->post($uri, [
                'json' => $data,
                'stream' => true,
            ]);

            if ($this->debug) {
                echo '[DEBUG] Streaming Response Status: '.$response->getStatusCode()."\n";
            }

            $buffer = '';
            $stream = $response->getBody();

            while (! $stream->eof()) {
                $chunk = $stream->read(1024);
                $buffer .= $chunk;

                // Process complete SSE messages
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $message = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    foreach (explode("\n", $message) as $line) {
                        if (str_starts_with($line, 'data: ')) {
                            $data = substr($line, 6);

                            if ($data === '[DONE]') {
                                if ($this->debug) {
                                    echo "[DEBUG] Streaming completed with [DONE] message\n";
                                }

                                return;
                            }

                            $decoded = json_decode($data, true);

                            if ($decoded !== null) {
                                if ($this->debug) {
                                    echo '[DEBUG] Streaming chunk: '.json_encode($decoded)."\n";
                                }

                                yield $decoded;
                            }
                        }
                    }
                }
            }
        } catch (GuzzleException $e) {
            if ($this->debug) {
                echo '[DEBUG] Streaming error: '.$e->getMessage()."\n";
            }

            throw new LMStudioException($e->getMessage(), $e->getCode(), $e);
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
            $response = $this->client->request($method, $uri, $options);
            $contents = $response->getBody()->getContents();

            if ($this->debug) {
                echo '[DEBUG] Response Body: '.$contents."\n";
            }

            return json_decode($contents, true);
        } catch (GuzzleException $e) {
            if ($this->debug) {
                echo '[DEBUG] Request error: '.$e->getMessage()."\n";
            }

            throw new LMStudioException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
