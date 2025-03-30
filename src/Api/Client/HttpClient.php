<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Client;

use Shelfwood\LMStudio\Api\Contract\HttpClientInterface;
use Shelfwood\LMStudio\Api\Exception\ApiException;
use Shelfwood\LMStudio\Api\Model\ChatCompletionChunk;

class HttpClient implements HttpClientInterface
{
    private StreamBuffer $buffer;

    public function __construct()
    {
        $this->buffer = new StreamBuffer;
    }

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
            $errorMessage = $this->extractErrorMessage($responseData);

            throw new ApiException('API Error: '.$errorMessage, $statusCode, null, $responseData);
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
     * @param  callable(ChatCompletionChunk): void  $callback  Callback function to handle each parsed chunk
     * @param  array  $headers  Additional headers
     *
     * @throws ApiException If the request fails
     */
    public function requestStream(string $method, string $endpoint, array $data, callable $callback, array $headers = []): void
    {
        $curl = curl_init();
        error_log('[DEBUG] Initializing stream request to: '.$endpoint);

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
            CURLOPT_WRITEFUNCTION => function ($curl, $data) use ($callback): int {
                try {
                    $length = strlen($data);
                    // error_log(sprintf('[DEBUG] Received chunk: %d bytes', $length)); // Optional: Keep/remove debug log

                    // Append the new data to our buffer
                    $this->buffer->append($data);

                    // Process complete lines from the buffer
                    while (($line = $this->buffer->readLine()) !== null) {
                        if (empty($line)) {
                            continue;
                        }

                        if ($line === 'data: [DONE]') {
                            // error_log('[DEBUG] Received DONE signal'); // Optional: Keep/remove debug log
                            // DONE signal doesn't need to be passed up typically
                            continue;
                        }

                        if (strpos($line, 'data: ') === 0) {
                            $jsonStr = substr($line, 6);
                            $jsonData = json_decode($jsonStr, true);

                            if (json_last_error() === JSON_ERROR_NONE && $jsonData !== null) {
                                // PARSE the raw JSON data into our model object
                                $chunkObject = ChatCompletionChunk::fromArray($jsonData);
                                // CALL the callback with the PARSED object
                                $callback($chunkObject);
                            } else {
                                error_log(sprintf('[WARNING] Invalid JSON in data line: %s', $line));
                                // Optionally, trigger an error event or throw?
                                // For now, just logging the warning.
                            }
                        }
                    }

                    // The buffer handles partial lines automatically, no need to clear here

                    return $length;
                } catch (\Throwable $e) {
                    // Log the error, but don't stop the stream if possible
                    error_log(sprintf('[ERROR] Exception during stream processing callback: %s', $e->getMessage()));
                    // Decide if the stream should terminate. Returning 0 or -1 might abort.
                    // Returning $length allows curl to continue.
                    return $length;
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
            error_log(sprintf('[DEBUG] Request payload: %s', $jsonData));
            $options[CURLOPT_POSTFIELDS] = $jsonData;
        }

        curl_setopt_array($curl, $options);

        error_log('[DEBUG] Starting cURL execution');
        $this->curlExec($curl);
        $err = $this->curlError($curl);
        $errno = curl_errno($curl);
        $statusCode = $this->curlGetInfo($curl, CURLINFO_HTTP_CODE);

        error_log(sprintf('[DEBUG] cURL execution completed (Status: %d)', $statusCode));

        if ($errno) {
            error_log(sprintf('[ERROR] cURL Error %d: %s', $errno, $err));
        }

        curl_close($curl);

        if ($err) {
            throw new ApiException('cURL Error: '.$err);
        }

        if ($statusCode >= 400) {
            throw new ApiException('API Error: HTTP status code '.$statusCode);
        }
    }

    private function extractErrorMessage(array $responseData): string
    {
        if (isset($responseData['error']['message'])) {
            return $responseData['error']['message'];
        }

        if (isset($responseData['error'])) {
            return is_string($responseData['error'])
                ? $responseData['error']
                : json_encode($responseData['error']);
        }

        return 'Unknown error';
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
