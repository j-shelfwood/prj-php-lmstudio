<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio;

use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;

class LMStudio
{
    private LMStudioConfig $config;

    private ?LMS $lms = null;

    private ?OpenAI $openai = null;

    public function __construct(?LMStudioConfig $config = null)
    {
        $this->config = $config ?? new LMStudioConfig;
    }

    /**
     * Get the native LMStudio API client (v0).
     */
    public function lms(): LMStudioClientInterface
    {
        if ($this->lms === null) {
            $this->lms = new LMS($this->config);
        }

        return $this->lms;
    }

    /**
     * Set the LMS client instance.
     */
    public function setLmsClient(LMS $client): self
    {
        $this->lms = $client;

        return $this;
    }

    /**
     * Get the OpenAI compatibility API client (v1).
     */
    public function openai(): LMStudioClientInterface
    {
        if ($this->openai === null) {
            $this->openai = new OpenAI($this->config);
        }

        return $this->openai;
    }

    /**
     * Set the OpenAI client instance.
     */
    public function setOpenAiClient(OpenAI $client): self
    {
        $this->openai = $client;

        return $this;
    }

    /**
     * Create a new instance with a different base URL.
     */
    public function withBaseUrl(string $baseUrl): self
    {
        $clone = clone $this;
        $clone->config = $this->config->withBaseUrl($baseUrl);
        $clone->lms = null;
        $clone->openai = null;

        return $clone;
    }

    /**
     * Create a new instance with a different API key.
     */
    public function withApiKey(string $apiKey): self
    {
        $clone = clone $this;
        $clone->config = $this->config->withApiKey($apiKey);
        $clone->lms = null;
        $clone->openai = null;

        return $clone;
    }

    /**
     * Create a new instance with different headers.
     *
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->config = $this->config->withHeaders($headers);
        $clone->lms = null;
        $clone->openai = null;

        return $clone;
    }

    /**
     * Create a new instance with a different timeout.
     */
    public function withTimeout(int $timeout): self
    {
        $clone = clone $this;
        $clone->config = $this->config->withTimeout($timeout);
        $clone->lms = null;
        $clone->openai = null;

        return $clone;
    }
}
