<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shelfwood\LMStudio\Exceptions\ConnectionException;

class ApiClient
{
    private Client $client;

    public function __construct(array $config)
    {
        $this->client = new Client($config);
    }

    public function get(string $uri, array $options = []): mixed
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

    public function post(string $uri, array $options = []): mixed
    {
        try {
            $response = $this->client->post(
                uri: $uri,
                options: $options
            );

            if (($options['stream'] ?? false) === true) {
                return $response;
            }

            // Use object format only for chat completion endpoints
            $assoc = ! str_contains($uri, '/chat/completions');

            // Always use array format for REST API endpoints
            if (str_starts_with($uri, '/api/v0')) {
                $assoc = true;
            }

            return $this->decode(json: $response->getBody()->getContents(), assoc: $assoc);
        } catch (GuzzleException $e) {
            throw ConnectionException::connectionFailed(
                message: "POST request to '{$uri}' failed: {$e->getMessage()}"
            );
        }
    }

    private function decode(string $json, bool $assoc = true): mixed
    {
        $data = json_decode($json, $assoc);

        if ($data === null) {
            throw ConnectionException::invalidResponse(
                message: 'Response is not a valid JSON: '.json_last_error_msg()
            );
        }

        return $data;
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
