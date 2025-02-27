<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Shelfwood\LMStudio\Config\LMStudioConfig;
use Shelfwood\LMStudio\Exceptions\LMStudioException;

class Client
{
    protected GuzzleClient $client;

    public function __construct(
        private LMStudioConfig $config
    ) {
        $this->client = new GuzzleClient([
            'base_uri' => $config->getBaseUrl(),
            'timeout' => $config->getTimeout(),
            'headers' => $config->getHeaders(),
        ]);
    }

    /**
     * Make a GET request.
     *
     * @throws LMStudioException
     */
    public function get(string $uri, array $query = []): array
    {
        return $this->request('GET', $uri, ['query' => $query]);
    }

    /**
     * Make a POST request.
     *
     * @throws LMStudioException
     */
    public function post(string $uri, array $data = []): array
    {
        return $this->request('POST', $uri, ['json' => $data]);
    }

    /**
     * Make a streaming POST request.
     *
     * @throws LMStudioException
     */
    public function stream(string $uri, array $data = []): \Generator
    {
        try {
            $response = $this->client->post($uri, [
                'json' => $data,
                'stream' => true,
            ]);

            $buffer = '';
            $stream = $response->getBody();

            while (! $stream->eof()) {
                $chunk = $stream->read(1024);
                $buffer .= $chunk;

                // Process complete SSE messages
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $message = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    foreach (explode("\n", $message) as $line) {
                        if (str_starts_with($line, 'data: ')) {
                            $data = substr($line, 6);

                            if ($data === '[DONE]') {
                                return;
                            }

                            $decoded = json_decode($data, true);

                            if ($decoded !== null) {
                                yield $decoded;
                            }
                        }
                    }
                }
            }
        } catch (GuzzleException $e) {
            throw new LMStudioException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Make a request.
     *
     * @throws LMStudioException
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $uri, $options);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new LMStudioException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
