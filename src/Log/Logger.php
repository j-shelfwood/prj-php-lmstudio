<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Log;

/**
 * Logger class for LMStudio API debugging
 */
class Logger
{
    /**
     * @var string|null The log file path
     */
    private ?string $logFile;

    /**
     * @var bool Whether verbose logging is enabled
     */
    private bool $verbose;

    /**
     * @var bool Whether logging is enabled
     */
    private bool $enabled;

    /**
     * Create a new Logger instance
     */
    public function __construct(
        bool $enabled = false,
        bool $verbose = false,
        ?string $logFile = null
    ) {
        $this->enabled = $enabled;
        $this->verbose = $verbose;
        $this->logFile = $logFile;

        // Create log directory if it doesn't exist
        if ($this->enabled && $this->logFile) {
            $logDir = dirname($this->logFile);

            if (! is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
        }
    }

    /**
     * Log a message
     */
    public function log(string $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[{$timestamp}] {$message}";

        // Add context if verbose is enabled
        if ($this->verbose && ! empty($context)) {
            $formattedContext = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $formattedMessage .= "\nContext: {$formattedContext}";
        }

        $formattedMessage .= "\n\n";

        // Write to log file if specified
        if ($this->logFile) {
            file_put_contents($this->logFile, $formattedMessage, FILE_APPEND);
        }

        // Also output to stderr for CLI applications
        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, $formattedMessage);
        }
    }

    /**
     * Log an API request
     */
    public function logRequest(string $method, string $url, array $options = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $message = "API Request: {$method} {$url}";
        $this->log($message, $options);
    }

    /**
     * Log an API response
     */
    public function logResponse(string $url, $response, float $duration = 0.0): void
    {
        if (! $this->enabled) {
            return;
        }

        $message = "API Response: {$url} (took {$duration}s)";
        $this->log($message, ['response' => $response]);
    }

    /**
     * Log an API error
     */
    public function logError(string $url, \Throwable $error, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $message = "API Error: {$url} - {$error->getMessage()}";
        $context['error'] = [
            'class' => get_class($error),
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString(),
        ];

        $this->log($message, $context);
    }

    /**
     * Log a tool call
     */
    public function logToolCall(string $toolName, array $arguments, $result = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $message = "Tool Call: {$toolName}";
        $context = [
            'arguments' => $arguments,
            'result' => $result,
        ];

        $this->log($message, $context);
    }

    /**
     * Log a streaming chunk
     */
    public function logStreamChunk($chunk, bool $hasContent = false, bool $hasToolCall = false): void
    {
        if (! $this->enabled || ! $this->verbose) {
            return;
        }

        $message = 'Stream Chunk Received';
        $context = [
            'chunk' => $chunk,
            'has_content' => $hasContent,
            'has_tool_call' => $hasToolCall,
        ];

        $this->log($message, $context);
    }

    /**
     * Create a logger from config
     */
    public static function fromConfig(array $config): self
    {
        $enabled = $config['enabled'] ?? false;
        $verbose = $config['verbose'] ?? false;
        $logFile = $config['log_file'] ?? null;

        return new self($enabled, $verbose, $logFile);
    }
}
