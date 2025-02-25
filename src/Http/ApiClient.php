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
     * @return array<string, mixed>|ResponseInterface
     */
    public function post(string $uri, array $options = []): array|ResponseInterface
    {
        try {
            $response = $this->client->post(
                uri: $uri,
                options: $options
            );

            if (($options['stream'] ?? false) === true) {
                return $response;
            }

            return $this->decode(json: $response->getBody()->getContents());
        } catch (GuzzleException $e) {
            throw ConnectionException::connectionFailed(
                message: "POST request to '{$uri}' failed: {$e->getMessage()}"
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        $data = json_decode($json, true);

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
