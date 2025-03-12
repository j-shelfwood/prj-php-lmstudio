<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Http\Request\V0;

use Shelfwood\LMStudio\Http\Request\Common\BaseRequest;

/**
 * Request for embeddings using the LM Studio API (v0).
 */
class EmbeddingRequest extends BaseRequest
{
    /**
     * @var array<string> The input to generate embeddings for
     */
    private array $input;

    /**
     * @var array<string, mixed> Additional options for the embedding
     */
    private array $options = [];

    /**
     * Create a new embedding request.
     *
     * @param  string|array<string>  $input  The input to generate embeddings for
     * @param  string  $model  The model to use
     * @param  array  $options  Additional options for the embedding
     */
    public function __construct(string|array $input, string $model, array $options = [])
    {
        $this->input = is_array($input) ? $input : [$input];
        $this->options = array_merge([
            'model' => $model,
        ], $options);
    }

    /**
     * Convert the request to an array.
     */
    public function jsonSerialize(): array
    {
        return array_merge($this->options, [
            'input' => $this->input,
        ]);
    }
}
