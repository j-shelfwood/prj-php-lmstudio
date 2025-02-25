<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Shelfwood\LMStudio\Contracts\ApiClientInterface;
use Shelfwood\LMStudio\Exceptions\ConnectionException;

class ApiClient implements ApiClientInterface
{
    private Client $client;

    public function __construct(array $config)
    {
        $this->client = new Client($config);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $uri, array $options = []): array
    {
        try {
            $response = $this->client->get(
                uri: $uri,
                options: $options
            );

            return $this->decode(json: $response->getBody()->getContents());
        } catch (GuzzleException $e) {
            throw ConnectionException::connectionFailed(
                message: "GET request to '{$uri}' failed: {$e->getMessage()}"
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function post(string $uri, array $options = []): array
    {
        try {
            $response = $this->client->post(
                uri: $uri,
                options: $options
            );

            // Handle streaming option by redirecting to postStreaming
            if (($options['stream'] ?? false) === true) {
                throw new \InvalidArgumentException(
                    'Use postStreaming() for streaming requests instead of post() with stream=true'
                );
            }

            return $this->decode(json: $response->getBody()->getContents());
        } catch (GuzzleException $e) {
            throw ConnectionException::connectionFailed(
                message: "POST request to '{$uri}' failed: {$e->getMessage()}"
            );
        }
    }

    /**
     * Send a POST request with streaming response
     */
    public function postStreaming(string $uri, array $options = []): ResponseInterface
    {
        // Ensure stream is set to true
        $options['stream'] = true;

        try {
            return $this->client->post(
                uri: $uri,
                options: $options
            );
        } catch (GuzzleException $e) {
            throw ConnectionException::connectionFailed(
                message: "Streaming POST request to '{$uri}' failed: {$e->getMessage()}"
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return $data;
        } catch (\JsonException $e) {
            throw ConnectionException::invalidResponse(
                message: 'Response is not a valid JSON: '.$e->getMessage()
            );
        }
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
