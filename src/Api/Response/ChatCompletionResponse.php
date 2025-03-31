<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Response;

use Shelfwood\LMStudio\Api\Enum\FinishReason;
use Shelfwood\LMStudio\Api\Model\Choice;
use Shelfwood\LMStudio\Api\Model\ResponseModelInfo;
use Shelfwood\LMStudio\Api\Model\RuntimeInfo;
use Shelfwood\LMStudio\Api\Model\Stats;
use Shelfwood\LMStudio\Api\Model\Usage;

/**
 * Represents a chat completion response.
 */
class ChatCompletionResponse
{
    /**
     * @param  string  $id  The ID of the completion
     * @param  string  $object  The object type
     * @param  int  $created  The timestamp when the completion was created
     * @param  string  $model  The model used for the completion
     * @param  array<Choice>  $choices  The choices in the completion
     * @param  Usage  $usage  The token usage information
     * @param  Stats|null  $stats  The performance statistics
     * @param  ResponseModelInfo|null  $modelInfo  The model information
     * @param  RuntimeInfo|null  $runtime  The runtime information
     * @param  string|null  $systemFingerprint  The system fingerprint
     */
    public function __construct(
        public readonly string $id,
        public readonly string $object,
        public readonly int $created,
        public readonly string $model,
        public readonly array $choices,
        public readonly Usage $usage,
        public readonly ?Stats $stats = null,
        public readonly ?ResponseModelInfo $modelInfo = null,
        public readonly ?RuntimeInfo $runtime = null,
        public readonly ?string $systemFingerprint = null,
    ) {}

    /**
     * Create a ChatCompletionResponse object from an array.
     *
     * @param  array  $data  The response data
     * @return self The created object
     */
    public static function fromArray(array $data): self
    {
        // Process choices
        $choicesData = $data['choices'] ?? [];
        $choices = [];

        foreach ($choicesData as $choiceData) {
            $choices[] = Choice::fromArray($choiceData);
        }

        return new self(
            id: $data['id'] ?? '',
            object: $data['object'] ?? 'chat.completion',
            created: $data['created'] ?? time(),
            model: $data['model'] ?? '',
            choices: $choices,
            usage: Usage::fromArray($data['usage'] ?? []),
            stats: Stats::fromArray($data['stats'] ?? null),
            modelInfo: ResponseModelInfo::fromArray($data['model_info'] ?? null),
            runtime: RuntimeInfo::fromArray($data['runtime'] ?? null),
            systemFingerprint: $data['system_fingerprint'] ?? null,
        );
    }

    /**
     * Get the choices in the completion.
     *
     * @return array<Choice>
     */
    public function getChoices(): array
    {
        return $this->choices;
    }

    /**
     * Get the token usage information.
     */
    public function getUsage(): Usage
    {
        return $this->usage;
    }

    /**
     * Get the performance statistics.
     */
    public function getStats(): ?Stats
    {
        return $this->stats;
    }

    /**
     * Get the model information.
     */
    public function getModelInfo(): ?ResponseModelInfo
    {
        return $this->modelInfo;
    }

    /**
     * Get the runtime information.
     */
    public function getRuntime(): ?RuntimeInfo
    {
        return $this->runtime;
    }

    /**
     * Get the first choice's content.
     */
    public function getContent(): ?string
    {
        if (empty($this->choices)) {
            return null;
        }

        return $this->choices[0]->getContent();
    }

    /**
     * Get the first choice's finish reason.
     */
    public function getFirstChoiceFinishReason(): ?FinishReason
    {
        return $this->choices[0]?->finishReason;
    }

    /**
     * Check if the response has tool calls.
     */
    public function hasToolCalls(): bool
    {
        if (empty($this->choices)) {
            return false;
        }

        return $this->choices[0]->hasToolCalls();
    }

    /**
     * Get the tool calls from the response.
     */
    public function getToolCalls(): array
    {
        if (empty($this->choices)) {
            return [];
        }

        return $this->choices[0]->getToolCalls();
    }
}
