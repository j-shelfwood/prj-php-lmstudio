<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api;

use Shelfwood\LMStudio\Contract\ApiClientInterface;
use Shelfwood\LMStudio\Contract\HttpClientInterface;
use Shelfwood\LMStudio\Exception\ApiException;

class ApiClient implements ApiClientInterface
{
    private HttpClientInterface $httpClient;
    private string $baseUrl;
    private array $defaultHeaders;

    /**
     * @param HttpClientInterface $httpClient The HTTP client
     * @param string $baseUrl The base URL of the API
     * @param array $defaultHeaders Default headers to include in all requests
     */
    public function __construct(
        HttpClientInterface $httpClient,
        string $baseUrl,
        array $defaultHeaders = []
    ) {
        $this->httpClient = $httpClient;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->defaultHeaders = array_merge(
            ['Content-Type' => 'application/json'],
            $defaultHeaders
        );
    }

    /**
     * Send a GET request to the API.
     *
     * @param string $endpoint API endpoint
     * @param array $headers Additional headers
     * @return array Response data
     * @throws ApiException If the request fails
     */
    public function get(string $endpoint, array $headers = []): array
    {
        return $this->request('GET', $endpoint, [], $headers);
    }

    /**
     * Send a POST request to the API.
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param array $headers Additional headers
     * @return array Response data
     * @throws ApiException If the request fails
     */
    public function post(string $endpoint, array $data, array $headers = []): array
    {
        return $this->request('POST', $endpoint, $data, $headers);
    }

    /**
     * Send a request to the API.
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param array $headers Additional headers
     * @return array Response data
     * @throws ApiException If the request fails
     */
    private function request(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        $mergedHeaders = array_merge($this->defaultHeaders, $headers);

        try {
            return $this->httpClient->request(
                $method,
                $this->baseUrl . $endpoint,
                $data,
                $mergedHeaders
            );
        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException('API request failed: ' . $e->getMessage(), 0, $e);
        }
    }
}