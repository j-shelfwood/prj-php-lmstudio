<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Client;

use CurlHandle;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shelfwood\LMStudio\Api\Contract\HttpClientInterface;
use Shelfwood\LMStudio\Api\Exception\ApiException;

// Define the type for cURL handles
use Shelfwood\LMStudio\Api\Model\ChatCompletionChunk;

class HttpClient implements HttpClientInterface
{
    private StreamBuffer $buffer;

    private LoggerInterface $logger;

    /**
     * Constructor with optional Logger injection.
     *
     * @param  LoggerInterface|null  $logger  PSR-3 Logger instance. Defaults to NullLogger.
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->buffer = new StreamBuffer;
        $this->logger = $logger ?? new NullLogger;
    }

    /**
     * Send a request to the API.
     *
     * @param  string  $method  HTTP method
     * @param  string  $endpoint  API endpoint
     * @param  array<string, mixed>  $data  Request data
     * @param  array<string, string>  $headers  Additional headers
     * @return array<string, mixed> Response data
     *
     * @throws ApiException If the request fails
     */
    public function request(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        $curl = $this->_curlInit();

        if (! $curl) {
            throw new ApiException('Failed to initialize cURL');
        }

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
        $curlHeaders = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        foreach ($headers as $key => $value) {
            $curlHeaders[] = "$key: $value";
        }

        $options[CURLOPT_HTTPHEADER] = $curlHeaders;

        // Add request body for POST, PUT, etc.
        if ($method !== 'GET' && ! empty($data)) {
            $jsonData = json_encode($data);

            if ($jsonData === false) {
                throw new ApiException('Failed to encode request data to JSON: '.json_last_error_msg());
            }
            $options[CURLOPT_POSTFIELDS] = $jsonData;
        }

        $this->_curlSetoptArray($curl, $options);

        $response = $this->_curlExec($curl);
        $err = $this->_curlError($curl);
        $statusCode = $this->_curlGetInfo($curl, CURLINFO_HTTP_CODE);

        $this->_curlClose($curl);

        if ($err) {
            throw new ApiException('cURL Error: '.$err);
        }

        // Ensure response is a string before decoding
        if (! is_string($response)) {
            // Handle cases where curl_exec might return true or false
            throw new ApiException('cURL execution did not return a string response.');
        }

        $responseData = json_decode($response, true);

        if ($statusCode >= 400) {
            $errorData = is_array($responseData) ? $responseData : []; // Ensure array for error extraction
            $errorMessage = $this->extractErrorMessage($errorData);

            throw new ApiException('API Error: '.$errorMessage, $statusCode, null, $responseData);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException('Invalid JSON response: '.json_last_error_msg());
        }

        // Ensure the returned value is an array
        return is_array($responseData) ? $responseData : [];
    }

    /**
     * Send a streaming request to the API.
     *
     * @param  string  $method  HTTP method
     * @param  string  $endpoint  API endpoint
     * @param  array<string, mixed>  $data  Request data
     * @param  callable(ChatCompletionChunk): void  $callback  Callback function to handle each parsed chunk
     * @param  array<string, string>  $headers  Additional headers
     *
     * @throws ApiException If the request fails
     */
    public function requestStream(string $method, string $endpoint, array $data, callable $callback, array $headers = []): void
    {
        $curl = $this->_curlInit();

        if (! $curl) {
            throw new ApiException('Failed to initialize cURL');
        }

        $this->logger->debug('Initializing stream request', ['endpoint' => $endpoint]);

        // Reset the buffer for this request
        $this->buffer = new StreamBuffer;

        $options = [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_WRITEFUNCTION => function ($curlHandle, $streamData) use ($callback): int {
                $length = 0; // Initialize length

                try {
                    if (! is_string($streamData)) {
                        $this->logger->warning('Non-string data received in stream callback');

                        return 0;
                    }
                    $length = strlen($streamData);

                    $this->buffer->append($streamData);

                    while (($line = $this->buffer->readLine()) !== null) {
                        if (empty($line)) {
                            continue;
                        }

                        if ($line === 'data: [DONE]') {
                            continue;
                        }

                        if (strpos($line, 'data: ') === 0) {
                            $jsonStr = substr($line, 6);
                            $jsonData = json_decode($jsonStr, true);

                            if (json_last_error() === JSON_ERROR_NONE && $jsonData !== null) {
                                $chunkObject = ChatCompletionChunk::fromArray($jsonData);
                                $callback($chunkObject);
                            } else {
                                $this->logger->warning('Invalid JSON in data line', ['line' => $line, 'json_error' => json_last_error_msg()]);
                            }
                        }
                    }

                    return $length;
                } catch (\Throwable $e) {
                    $this->logger->error('Exception during stream processing callback', ['exception' => $e]);

                    // Ensure $length is defined even if exception occurs before assignment
                    return $length; // Allow curl to continue, but log the error
                }
            },
        ];

        // Set up headers for SSE
        $curlHeaders = [
            'Accept: text/event-stream',
            'Cache-Control: no-cache',
            'Content-Type: application/json',
        ];

        foreach ($headers as $key => $value) {
            $curlHeaders[] = "$key: $value";
        }

        $options[CURLOPT_HTTPHEADER] = $curlHeaders;

        // Add request body for POST, PUT, etc.
        if ($method !== 'GET' && ! empty($data)) {
            $jsonData = json_encode($data);

            if ($jsonData === false) {
                throw new ApiException('Failed to encode stream request data to JSON: '.json_last_error_msg());
            }
            $this->logger->debug('Request payload', ['payload' => $jsonData]);
            $options[CURLOPT_POSTFIELDS] = $jsonData;
        }

        $this->_curlSetoptArray($curl, $options);

        $this->logger->debug('Starting cURL execution');
        $this->_curlExec($curl);
        $err = $this->_curlError($curl);
        $errno = $this->_curlErrno($curl);
        $statusCode = $this->_curlGetInfo($curl, CURLINFO_HTTP_CODE);

        $this->logger->debug('cURL execution completed', ['status' => $statusCode]);

        if ($errno) {
            $this->logger->error('cURL stream error', ['errno' => $errno, 'error' => $err]);
        }

        $this->_curlClose($curl);

        if ($err) {
            throw new ApiException('cURL Error: '.$err);
        }

        if ($statusCode >= 400) {
            throw new ApiException('API Error: HTTP status code '.$statusCode);
        }
    }

    /**
     * Extract error message from response data.
     *
     * @param  array<string, mixed>  $responseData  Decoded JSON response data
     * @return string Error message
     */
    private function extractErrorMessage(array $responseData): string
    {
        // Prioritize LMStudio's specific error format
        if (isset($responseData['error']['message']) && is_string($responseData['error']['message'])) {
            return $responseData['error']['message'];
        }

        // Fallback: Try common 'message' key
        if (isset($responseData['message']) && is_string($responseData['message'])) {
            return $responseData['message'];
        }

        // Fallback: Try common 'error' key (if it's a string)
        if (isset($responseData['error']) && is_string($responseData['error'])) {
            return $responseData['error'];
        }

        // Final fallback: stringify the response
        return json_encode($responseData) ?: 'Unknown error structure';
    }

    // --- cURL Wrapper Methods --- (for testability)

    /**
     * Wrapper for curl_init().
     *
     * @return CurlHandle|false
     */
    protected function _curlInit()
    {
        return curl_init();
    }

    /**
     * Wrapper for curl_setopt_array().
     */
    protected function _curlSetoptArray(CurlHandle $curl, array $options): bool
    {
        return curl_setopt_array($curl, $options);
    }

    /**
     * Wrapper for curl_exec().
     *
     * @return bool|string
     */
    protected function _curlExec(CurlHandle $curl)
    {
        return curl_exec($curl);
    }

    /**
     * Wrapper for curl_error().
     */
    protected function _curlError(CurlHandle $curl): string
    {
        return curl_error($curl);
    }

    /**
     * Wrapper for curl_errno().
     */
    protected function _curlErrno(CurlHandle $curl): int
    {
        return curl_errno($curl);
    }

    /**
     * Wrapper for curl_getinfo().
     *
     * @return mixed
     */
    protected function _curlGetInfo(CurlHandle $curl, int $option)
    {
        return curl_getinfo($curl, $option);
    }

    /**
     * Wrapper for curl_close().
     */
    protected function _curlClose(CurlHandle $curl): void
    {
        curl_close($curl);
    }
}
