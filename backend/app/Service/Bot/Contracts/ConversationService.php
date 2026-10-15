<?php

namespace App\Service\Bot\Contracts;

use App\Models\Bot\Conversation;
use App\Models\Bot\Message;

interface ConversationService
{
    /**
     * List all conversations with optional filters (status, search, device_id).
     *
     * @param array $filters
     * @return array
     */
    public function list(array $filters = []): array;

    /**
     * Get a single conversation by ID with its messages.
     *
     * @param string $conversationId
     * @return Conversation
     */
    public function show(string $conversationId): Conversation;

    /**
     * Get message history for a conversation.
     *
     * @param string $conversationId
     * @param int $limit
     * @return array
     */
    public function getMessages(string $conversationId, int $limit = 50): array;

    /**
     * Send an outgoing message in a conversation via WhatsApp Gateway and persist to DB.
     *
     * @param string $conversationId
     * @param string $text
     * @param string|null $senderType
     * @return Message
     */
    public function sendMessage(string $conversationId, string $text, ?string $senderType = 'human'): Message;

    /**
     * Synchronize conversations and recent messages from GOWA into the local database.
     *
     * @param string|null $deviceId
     * @return array
     */
    public function syncFromGowa(?string $deviceId = null): array;

    /**
     * Process an incoming message webhook payload from GOWA and persist to DB.
     *
     * @param array $payload
     * @param string|null $deviceId
     * @return Message|null
     */
    public function handleIncomingMessageWebhook(array $payload, ?string $deviceId = null): ?Message;
}
