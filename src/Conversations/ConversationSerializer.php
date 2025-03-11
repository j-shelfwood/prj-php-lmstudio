<?php

declare(strict_types=1);

namespace Shelfwood\LMStudio\Conversations;

use Shelfwood\LMStudio\Contracts\LMStudioClientInterface;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;

/**
 * Handles serialization and deserialization of conversations.
 */
class ConversationSerializer
{
    /**
     * Convert a conversation to JSON.
     */
    public function toJson(Conversation $conversation): string
    {
        return json_encode([
            'id' => $conversation->getId(),
            'title' => $conversation->getTitle(),
            'created_at' => $conversation->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $conversation->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'metadata' => $conversation->getAllMetadata(),
            'messages' => $conversation->getHistory()->jsonSerialize(),
            'model' => $conversation->getModel(),
            'temperature' => $conversation->getTemperature(),
            'max_tokens' => $conversation->getMaxTokens(),
        ]);
    }

    /**
     * Create a conversation from JSON.
     */
    public function fromJson(string $json, LMStudioClientInterface $client): Conversation
    {
        $data = json_decode($json, true);

        if (! $data) {
            throw new \InvalidArgumentException('Invalid JSON');
        }

        $history = new ChatHistory;

        foreach ($data['messages'] as $messageData) {
            $role = $messageData['role'];
            $content = $messageData['content'] ?? null;

            if ($role === 'system') {
                $history->addSystemMessage($content);
            } elseif ($role === 'user') {
                $history->addUserMessage($content, $messageData['name'] ?? null);
            } elseif ($role === 'assistant') {
                $history->addAssistantMessage($content, $messageData['tool_calls'] ?? null);
            } elseif ($role === 'tool') {
                $history->addToolMessage($content, $messageData['tool_call_id']);
            }
        }

        $conversation = new Conversation(
            client: $client,
            title: $data['title'],
            id: $data['id'],
            history: $history
        );

        // Set additional properties
        if (isset($data['model'])) {
            $conversation->setModel($data['model']);
        }

        if (isset($data['temperature'])) {
            $conversation->setTemperature((float) $data['temperature']);
        }

        if (isset($data['max_tokens'])) {
            $conversation->setMaxTokens((int) $data['max_tokens']);
        }

        if (isset($data['metadata'])) {
            foreach ($data['metadata'] as $key => $value) {
                $conversation->setMetadata($key, $value);
            }
        }

        return $conversation;
    }
}
