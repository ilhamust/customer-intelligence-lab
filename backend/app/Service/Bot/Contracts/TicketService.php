<?php

namespace App\Service\Bot\Contracts;

use App\Models\Bot\Conversation;
use App\Models\Bot\Ticket;

interface TicketService
{
    /**
     * Create a new ticket, calculate FIFO queue position, and return the ticket.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function createTicket(
        Conversation $conversation,
        string $category = 'general_human',
        string $priority = 'need_human',
        ?string $notes = null,
        ?array $metadata = null
    ): Ticket;

    /**
     * Start serving a queued ticket: change status to in_progress, switch conversation handler to 'human',
     * and send greeting message to customer.
     */
    public function startServingTicket(string $ticketId, ?string $adminName = 'Admin Customer Care'): Ticket;

    /**
     * Resolve an active ticket: change status to resolved, restore conversation handler to 'ai',
     * and send closing resolution message to customer.
     */
    public function resolveTicket(string $ticketId, ?string $resolutionNotes = null): Ticket;

    /**
     * List all tickets with optional filtering by status, category, search, and queue summary.
     *
     * @param  array{
     *     status?: string,
     *     category?: string,
     *     search?: string,
     *     page?: int,
     *     per_page?: int
     * }  $filters
     * @return array{
     *     data: list<array<string, mixed>>,
     *     summary: array{
     *         total: int,
     *         queued: int,
     *         in_progress: int,
     *         resolved: int
     *     }
     * }
     */
    public function listTickets(array $filters = []): array;

    /**
     * Get active ticket (queued or in_progress) for a given conversation if any.
     */
    public function getActiveTicketForConversation(string $conversationId): ?Ticket;

    /**
     * Get the dynamic queue position for a ticket among all queued tickets.
     */
    public function calculateQueuePosition(Ticket $ticket): int;

    /**
     * Build the official WhatsApp message text informing the customer about their queue number.
     */
    public function buildQueueNotificationText(int $queuePosition, string $category, ?string $customerName = 'Pelanggan'): string;

    /**
     * Build the official WhatsApp greeting text when CS starts serving the ticket.
     */
    public function buildCsGreetingText(string $customerName, string $category, string $adminName = 'Admin Customer Care'): string;

    /**
     * Build the official WhatsApp resolution closing text when ticket is resolved.
     */
    public function buildResolutionClosingText(string $customerName, ?string $resolutionNotes = null): string;

    /**
     * Get the human-readable Indonesian label for a ticket category.
     */
    public function getCategoryLabel(string $category): string;
}
