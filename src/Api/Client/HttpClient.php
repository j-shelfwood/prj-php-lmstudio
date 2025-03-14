<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Client;

use Shelfwood\LMStudio\Api\Contract\HttpClientInterface;
use Shelfwood\LMStudio\Api\Exception\ApiException;

class HttpClient implements HttpClientInterface
{
    /**
     * Send a request to the API.
     *
     * @param  string  $method  HTTP method
     * @param  string  $endpoint  API endpoint
     * @param  array  $data  Request data
     * @param  array  $headers  Additional headers
     * @return array Response data
     *
     * @throws ApiException If the request fails
     */
    public function request(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        $curl = curl_init();

        $options = [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
        ];

        // Convert headers array to cURL format
        $curlHeaders = [];

        foreach ($headers as $key => $value) {
            $curlHeaders[] = "$key: $value";
        }

        if (! empty($curlHeaders)) {
            $options[CURLOPT_HTTPHEADER] = $curlHeaders;
        }

        // Add request body for POST, PUT, etc.
        if ($method !== 'GET' && ! empty($data)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($curl, $options);

        $response = $this->curlExec($curl);
        $err = $this->curlError($curl);
        $statusCode = $this->curlGetInfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($err) {
            throw new ApiException('cURL Error: '.$err);
        }

        $responseData = json_decode($response, true);

        if ($statusCode >= 400) {
            // Extract error message from response
            $errorMessage = 'Unknown error';

            if (is_array($responseData)) {
                if (isset($responseData['error']['message'])) {
                    $errorMessage = $responseData['error']['message'];
                } elseif (isset($responseData['error'])) {
                    $errorMessage = is_string($responseData['error']) ? $responseData['error'] : json_encode($responseData['error']);
                }
            }

            throw new ApiException(
                'API Error: '.$errorMessage,
                $statusCode,
                null,
                $responseData
            );
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException('Invalid JSON response: '.json_last_error_msg());
        }

        return $responseData;
    }

    /**
     * Send a streaming request to the API.
     *
     * @param  string  $method  HTTP method
     * @param  string  $endpoint  API endpoint
     * @param  array  $data  Request data
     * @param  callable  $callback  Callback function to handle each chunk of data
     * @param  array  $headers  Additional headers
     *
     * @throws ApiException If the request fails
     */
    public function requestStream(string $method, string $endpoint, array $data, callable $callback, array $headers = []): void
    {
        $curl = curl_init();

        $options = [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0, // No timeout for streaming
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_WRITEFUNCTION => function ($curl, $data) use ($callback) {
                // Each chunk of data should be a complete JSON object
                $data = trim($data);

                // Skip empty lines and "data: [DONE]" messages
                if (empty($data) || $data === 'data: [DONE]') {
                    return strlen($data);
                }

                // Remove "data: " prefix if present
                if (strpos($data, 'data: ') === 0) {
                    $data = substr($data, 6);
                }

                // Parse JSON data
                $jsonData = json_decode($data, true);

                if (json_last_error() === JSON_ERROR_NONE && $jsonData !== null) {
                    $callback($jsonData);
                }

                return strlen($data);
            },
        ];

        // Convert headers array to cURL format
        $curlHeaders = [];

        foreach ($headers as $key => $value) {
            $curlHeaders[] = "$key: $value";
        }

        if (! empty($curlHeaders)) {
            $options[CURLOPT_HTTPHEADER] = $curlHeaders;
        }

        // Add request body for POST, PUT, etc.
        if ($method !== 'GET' && ! empty($data)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($curl, $options);

        $this->curlExec($curl);
        $err = $this->curlError($curl);
        $statusCode = $this->curlGetInfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($err) {
            throw new ApiException('cURL Error: '.$err);
        }

        if ($statusCode >= 400) {
            throw new ApiException('API Error: HTTP status code '.$statusCode);
        }
    }

    /**
     * Execute a cURL request.
     *
     * @param  \CurlHandle  $curl  The cURL resource
     * @return string|bool The response or false on failure
     */
    protected function curlExec(\CurlHandle $curl)
    {
        return curl_exec($curl);
    }

    /**
     * Get cURL error message.
     *
     * @param  \CurlHandle  $curl  The cURL resource
     * @return string The error message
     */
    protected function curlError(\CurlHandle $curl): string
    {
        return curl_error($curl);
    }

    /**
     * Get cURL info.
     *
     * @param  \CurlHandle  $curl  The cURL resource
     * @param  int  $option  The option to get
     * @return mixed The info
     */
    protected function curlGetInfo(\CurlHandle $curl, int $option)
    {
        return curl_getinfo($curl, $option);
    }
}
