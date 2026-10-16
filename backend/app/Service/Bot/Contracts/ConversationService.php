<?php

namespace App\Service\Bot\Contracts;

use App\Models\Bot\Conversation;
use App\Models\Bot\Message;

interface ConversationService
{
    /**
     * List all conversations with optional filters (status, search, device_id).
     */
    public function list(array $filters = []): array;

    /**
     * Get a single conversation by ID with its messages.
     */
    public function show(string $conversationId): Conversation;

    /**
     * Get message history for a conversation.
     */
    public function getMessages(string $conversationId, int $limit = 50): array;

    /**
     * Send an outgoing message in a conversation via WhatsApp Gateway and persist to DB.
     */
    public function sendMessage(string $conversationId, string $text, ?string $senderType = 'human'): Message;

    /**
     * Synchronize conversations and recent messages from GOWA into the local database.
     */
    public function syncFromGowa(?string $deviceId = null): array;

    /**
     * Process an incoming message webhook payload from GOWA and persist to DB.
     */
    public function handleIncomingMessageWebhook(array $payload, ?string $deviceId = null): ?Message;

    /**
     * Handle typing presence indicator and auto-takeover for human operator.
     *
     * @param  string  $action  'start' | 'stop'
     */
    public function handleTyping(string $conversationId, string $action): array;

    /**
     * Update conversation handler mode ('ai' or 'human') manually.
     */
    public function updateHandler(string $conversationId, string $handler): array;
}
