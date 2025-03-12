<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Service;

use Shelfwood\LMStudio\Exception\ApiException;
use Shelfwood\LMStudio\Exception\ValidationException;
use Shelfwood\LMStudio\Model\Message;
use Shelfwood\LMStudio\Model\Tool;
use Shelfwood\LMStudio\Response\ChatCompletionResponse;

class ChatService extends AbstractService
{
    /**
     * Create a chat completion.
     *
     * @param string $model The model to use
     * @param array $messages The messages to send
     * @param array $options Additional options
     * @return ChatCompletionResponse
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
}