<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Debug logger for LMStudio API client.
 */
class DebugLogger
{
    /**
     * @var LoggerInterface The PSR-3 logger instance
     */
    private LoggerInterface $logger;

    /**
     * @var bool Whether debug mode is enabled
     */
    private bool $enabled;

    /**
     * @var bool Whether verbose logging is enabled
     */
    private bool $verbose;

    /**
     * @var resource|null File handle for log file
     */
    private $fileHandle;

    /**
     * Create a new debug logger.
     *
     * @param  bool  $enabled  Whether debug mode is enabled
     * @param  bool  $verbose  Whether verbose logging is enabled
     * @param  string|null  $logFile  Path to log file (null for stdout)
     * @param  LoggerInterface|null  $logger  PSR-3 logger instance (null for internal logger)
     */
    public function __construct(
        bool $enabled = false,
        bool $verbose = false,
        ?string $logFile = null,
        ?LoggerInterface $logger = null
    ) {
        $this->enabled = $enabled;
        $this->verbose = $verbose;
        $this->logger = $logger ?? new NullLogger;

        if ($enabled && $logFile !== null) {
            $fileHandle = @fopen($logFile, 'a');

            if ($fileHandle === false) {
                $this->fileHandle = null;
                $this->log('Warning: Could not open log file: '.$logFile);
            } else {
                $this->fileHandle = $fileHandle;
            }
        }
    }

    /**
     * Log a debug message.
     *
     * @param  string  $message  The message to log
     * @param  array  $context  Additional context data
     */
    public function log(string $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[{$timestamp}] {$message}";

        if ($this->verbose && ! empty($context)) {
            $formattedMessage .= "\nContext: ".json_encode($context, JSON_PRETTY_PRINT);
        }

        // Log to file if available
        if ($this->fileHandle) {
            fwrite($this->fileHandle, $formattedMessage.PHP_EOL);
        } else {
            // Log to stdout
            echo $formattedMessage.PHP_EOL;
        }

        // Log to PSR-3 logger
        $this->logger->debug($message, $context);
    }

    /**
     * Log a request.
     *
     * @param  string  $method  The HTTP method
     * @param  string  $uri  The request URI
     * @param  array  $data  The request data
     */
    public function logRequest(string $method, string $uri, array $data = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->log("Request: {$method} {$uri}", [
            'data' => $this->verbose ? $data : $this->summarizeData($data),
        ]);
    }

    /**
     * Log a response.
     *
     * @param  int  $statusCode  The HTTP status code
     * @param  mixed  $body  The response body
     */
    public function logResponse(int $statusCode, $body): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->log("Response: Status {$statusCode}", [
            'body' => $this->verbose ? $body : $this->summarizeData($body),
        ]);
    }

    /**
     * Log a streaming chunk.
     *
     * @param  mixed  $chunk  The streaming chunk
     * @param  int  $chunkNumber  The chunk number
     */
    public function logStreamingChunk($chunk, int $chunkNumber): void
    {
        if (! $this->enabled || ! $this->verbose) {
            return;
        }

        $this->log("Streaming chunk #{$chunkNumber}", [
            'chunk' => $chunk,
        ]);
    }

    /**
     * Log an error.
     *
     * @param  string  $message  The error message
     * @param  \Throwable|null  $exception  The exception that caused the error
     */
    public function logError(string $message, ?\Throwable $exception = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $context = [];

        if ($exception) {
            $context['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];

            if ($this->verbose) {
                $context['exception']['trace'] = $exception->getTraceAsString();
            }
        }

        $this->log("ERROR: {$message}", $context);
    }

    /**
     * Summarize data for non-verbose logging.
     *
     * @param  mixed  $data  The data to summarize
     * @return mixed The summarized data
     */
    private function summarizeData($data)
    {
        if (is_array($data)) {
            $result = [];

            foreach ($data as $key => $value) {
                if (is_array($value) && count($value) > 3) {
                    $result[$key] = '[Array with '.count($value).' items]';
                } elseif (is_string($value) && strlen($value) > 100) {
                    $result[$key] = substr($value, 0, 100).'... [truncated]';
                } else {
                    $result[$key] = $value;
                }
            }

            return $result;
        }

        if (is_string($data) && strlen($data) > 100) {
            return substr($data, 0, 100).'... [truncated]';
        }

        return $data;
    }

    /**
     * Check if debug mode is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Check if verbose logging is enabled.
     */
    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    /**
     * Clean up resources.
     */
    public function __destruct()
    {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
        }
    }
}
