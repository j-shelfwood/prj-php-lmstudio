<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Api\Service;

use Shelfwood\LMStudio\Api\Exception\ApiException;
use Shelfwood\LMStudio\Api\Exception\ValidationException;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Response\ChatCompletionResponse;

class ChatService extends AbstractService
{
    /**
     * Create a chat completion.
     *
     * @param  string  $model  The model to use
     * @param  array  $messages  The messages to send
     * @param  array  $options  Additional options
     *
     * @throws ApiException If the request fails
     * @throws ValidationException If the request is invalid
     */
    public function createCompletion(string $model, array $messages, array $options = []): ChatCompletionResponse
    {
        if (empty($model)) {
            throw new ValidationException('Model is required');
        }

        if (empty($messages)) {
            throw new ValidationException('Messages are required');
        }

        // Convert Message objects to arrays
        $messageData = array_map(function ($message) {
            return $message instanceof Message ? $message->toArray() : $message;
        }, $messages);

        // Convert Tool objects to arrays
        if (isset($options['tools'])) {
            $options['tools'] = array_map(function ($tool) {
                return $tool instanceof Tool ? $tool->toArray() : $tool;
            }, $options['tools']);
        }

        $data = array_merge([
            'model' => $model,
            'messages' => $messageData,
        ], $options);

        $response = $this->apiClient->post('/api/v0/chat/completions', $data);

        return ChatCompletionResponse::fromArray($response);
    }

    /**
     * Create a streaming chat completion.
     *
     * @param  string  $model  The model to use
     * @param  array  $messages  The messages to send
     * @param  array  $options  Additional options
     * @param  callable  $callback  Callback function to handle each chunk of data
     *
     * @throws ApiException If the request fails
     * @throws ValidationException If the request is invalid
     */
    public function createCompletionStream(string $model, array $messages, array $options = [], ?callable $callback = null): void
    {
        if (empty($model)) {
            throw new ValidationException('Model is required');
        }

        if (empty($messages)) {
            throw new ValidationException('Messages are required');
        }

        if ($callback === null) {
            throw new ValidationException('Callback is required for streaming');
        }

        // Ensure streaming is enabled
        $options['stream'] = true;

        // Convert Message objects to arrays
        $messageData = array_map(function ($message) {
            return $message instanceof Message ? $message->toArray() : $message;
        }, $messages);

        // Convert Tool objects to arrays
        if (isset($options['tools'])) {
            $options['tools'] = array_map(function ($tool) {
                return $tool instanceof Tool ? $tool->toArray() : $tool;
            }, $options['tools']);
        }

        $data = array_merge([
            'model' => $model,
            'messages' => $messageData,
        ], $options);

        $this->apiClient->postStream('/api/v0/chat/completions', $data, $callback);
    }
}
