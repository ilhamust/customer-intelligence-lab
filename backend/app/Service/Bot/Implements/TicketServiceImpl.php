<?php

namespace App\Service\Bot\Implements;

use App\Events\Bot\ConversationUpdated;
use App\Events\Bot\MessageReceived;
use App\Events\Bot\TicketQueueUpdated;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Models\Bot\Conversation;
use App\Models\Bot\Message;
use App\Models\Bot\Ticket;
use App\Service\Bot\Contracts\TicketService;
use App\Service\Whatsapp\Contracts\WhatsappGatewayService;
use Illuminate\Support\Facades\Log;

class TicketServiceImpl implements TicketService
{
    public function __construct(
        protected WhatsappGatewayService $whatsappGateway
    ) {}

    /**
     * Create a new ticket or return existing active ticket, calculating FIFO queue position.
     */
    public function createTicket(
        Conversation $conversation,
        string $category = 'general_human',
        string $priority = 'need_human',
        ?string $notes = null,
        ?array $metadata = null
    ): Ticket {
        // Cek apakah percakapan sudah memiliki tiket aktif (queued atau in_progress)
        $existing = Ticket::where('conversation_id', $conversation->id)
            ->whereIn('status', ['queued', 'in_progress'])
            ->first();

        if ($existing) {
            Log::info('[TicketService::createTicket] Conversation already has an active ticket', [
                'conversation_id' => $conversation->id,
                'ticket_code' => $existing->ticket_code,
                'status' => $existing->status,
            ]);

            return $existing;
        }

        // Hitung urutan antrean relatif
        $queuePosition = Ticket::where('status', 'queued')->count() + 1;

        $ticket = Ticket::create([
            'ticket_code' => Ticket::generateTicketCode(),
            'conversation_id' => $conversation->id,
            'customer_id' => $conversation->customer_id,
            'category' => $category,
            'priority' => $priority,
            'status' => 'queued',
            'queue_position' => $queuePosition,
            'resolution_notes' => $notes,
            'metadata' => $metadata,
        ]);

        Log::info('[TicketService::createTicket] Ticket created successfully', [
            'ticket_id' => $ticket->id,
            'ticket_code' => $ticket->ticket_code,
            'category' => $category,
            'queue_position' => $queuePosition,
        ]);

        // Broadcast antrean baru ke dashboard via Reverb
        broadcast(new TicketQueueUpdated($ticket, 'created'));

        return $ticket;
    }

    /**
     * Start serving a queued ticket: change status to in_progress, switch conversation handler to 'human',
     * and send greeting message to customer.
     */
    public function startServingTicket(string $ticketId, ?string $adminName = 'Admin Customer Care'): Ticket
    {
        $ticket = Ticket::with(['conversation.customer', 'customer'])->find($ticketId);

        if (! $ticket) {
            throw new NotFoundException("Ticket [{$ticketId}] not found.");
        }

        if ($ticket->status === 'resolved') {
            throw new BadRequestException("Ticket [{$ticket->ticket_code}] has already been resolved.");
        }

        $conversation = $ticket->conversation;
        if (! $conversation) {
            throw new NotFoundException("Associated conversation for ticket [{$ticketId}] not found.");
        }

        // Update status tiket
        $ticket->update([
            'status' => 'in_progress',
        ]);

        // Alihkan mode percakapan ke human
        $conversation->update([
            'handler' => 'human',
            'status' => 'escalated',
        ]);

        // Kirim salam pembuka resmi Admin CS ke WhatsApp pelanggan
        $customerName = $ticket->customer?->name ?: ($conversation->name ?: 'Pelanggan');
        $greetingText = $this->buildCsGreetingText($customerName, $ticket->category, $adminName ?: 'Admin Customer Care');

        $this->sendAndPersistMessage($conversation, $greetingText);

        Log::info('[TicketService::startServingTicket] Admin started serving ticket', [
            'ticket_id' => $ticket->id,
            'ticket_code' => $ticket->ticket_code,
            'conversation_id' => $conversation->id,
            'admin_name' => $adminName,
        ]);

        // Broadcast pembaruan status percakapan dan antrean tiket via Reverb
        broadcast(new ConversationUpdated($conversation));
        broadcast(new TicketQueueUpdated($ticket, 'serving'));

        return $ticket->fresh(['conversation.customer', 'customer']);
    }

    /**
     * Resolve an active ticket: change status to resolved, restore conversation handler to 'ai',
     * and send closing resolution message to customer.
     */
    public function resolveTicket(string $ticketId, ?string $resolutionNotes = null): Ticket
    {
        $ticket = Ticket::with(['conversation.customer', 'customer'])->find($ticketId);

        if (! $ticket) {
            throw new NotFoundException("Ticket [{$ticketId}] not found.");
        }

        $conversation = $ticket->conversation;
        if (! $conversation) {
            throw new NotFoundException("Associated conversation for ticket [{$ticketId}] not found.");
        }

        // Update status tiket
        $ticket->update([
            'status' => 'resolved',
            'resolution_notes' => $resolutionNotes ?: $ticket->resolution_notes,
            'resolved_at' => now(),
        ]);

        // Kembalikan mode percakapan ke bot AI
        $conversation->update([
            'handler' => 'ai',
            'status' => 'active',
        ]);

        // Kirim salam penutup penyelesaian tiket ke WhatsApp pelanggan
        $customerName = $ticket->customer?->name ?: ($conversation->name ?: 'Pelanggan');
        $closingText = $this->buildResolutionClosingText($customerName, $resolutionNotes);

        $this->sendAndPersistMessage($conversation, $closingText);

        Log::info('[TicketService::resolveTicket] Ticket resolved and closed', [
            'ticket_id' => $ticket->id,
            'ticket_code' => $ticket->ticket_code,
            'conversation_id' => $conversation->id,
            'resolved_at' => $ticket->resolved_at,
        ]);

        // Broadcast pembaruan status percakapan dan antrean tiket via Reverb
        broadcast(new ConversationUpdated($conversation));
        broadcast(new TicketQueueUpdated($ticket, 'resolved'));

        return $ticket->fresh(['conversation.customer', 'customer']);
    }

    /**
     * List all tickets with optional filtering by status, category, search, and queue summary.
     */
    public function listTickets(array $filters = []): array
    {
        $query = Ticket::with(['conversation', 'customer']);

        // Filter status
        $status = $filters['status'] ?? 'all';
        if (in_array($status, ['queued', 'in_progress', 'resolved', 'closed'], true)) {
            $query->where('status', $status);
        }

        // Filter kategori
        if (! empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        // Filter pencarian
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('ticket_code', 'ilike', "%{$search}%")
                    ->orWhere('category', 'ilike', "%{$search}%")
                    ->orWhereHas('customer', function ($sub) use ($search) {
                        $sub->where('name', 'ilike', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('customer_code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('conversation', function ($sub) use ($search) {
                        $sub->where('name', 'ilike', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $tickets = $query->orderByRaw("
            CASE 
                WHEN status = 'in_progress' THEN 1 
                WHEN status = 'queued' THEN 2 
                ELSE 3 
            END ASC
        ")->orderBy('created_at', 'asc')->get();

        $data = $tickets->map(fn (Ticket $t) => $this->formatTicket($t))->all();

        return [
            'data' => $data,
            'summary' => [
                'total' => Ticket::count(),
                'queued' => Ticket::where('status', 'queued')->count(),
                'in_progress' => Ticket::where('status', 'in_progress')->count(),
                'resolved' => Ticket::where('status', 'resolved')->count(),
            ],
        ];
    }

    /**
     * Get active ticket (queued or in_progress) for a given conversation if any.
     */
    public function getActiveTicketForConversation(string $conversationId): ?Ticket
    {
        return Ticket::with(['conversation.customer', 'customer'])
            ->where('conversation_id', $conversationId)
            ->whereIn('status', ['queued', 'in_progress'])
            ->latest('created_at')
            ->first();
    }

    /**
     * Get the dynamic queue position for a ticket among all queued tickets.
     */
    public function calculateQueuePosition(Ticket $ticket): int
    {
        if ($ticket->status !== 'queued') {
            return 0;
        }

        return Ticket::where('status', 'queued')
            ->where('created_at', '<=', $ticket->created_at)
            ->count();
    }

    /**
     * Build the official WhatsApp message text informing the customer about their queue number.
     */
    public function buildQueueNotificationText(int $queuePosition, string $category, ?string $customerName = 'Pelanggan'): string
    {
        $categoryLabel = $this->getCategoryLabel($category);

        return <<<TEXT
Halo Kak *{$customerName}*,

Pengaduan Kakak mengenai *{$categoryLabel}* telah diteruskan ke Customer Service kami.

Saat ini Kakak berada dalam antrean nomor:
*#{$queuePosition}*

Mohon ditunggu sebentar ya Kak, tim kami akan segera melayani Kakak. Terima kasih atas kesabarannya 🙏
TEXT;
    }

    /**
     * Build the official WhatsApp greeting text when CS starts serving the ticket.
     */
    public function buildCsGreetingText(string $customerName, string $category, string $adminName = 'Admin Customer Care'): string
    {
        $categoryLabel = $this->getCategoryLabel($category);

        return <<<TEXT
Halo Kak *{$customerName}*, perkenalkan saya *{$adminName}* dari Customer Care Jarvis.

Saya akan membantu Kakak terkait pengaduan *{$categoryLabel}*. Ada informasi tambahan atau kronologi kendala yang ingin Kakak sampaikan?
TEXT;
    }

    /**
     * Build the official WhatsApp resolution closing text when ticket is resolved.
     */
    public function buildResolutionClosingText(string $customerName, ?string $resolutionNotes = null): string
    {
        $notesText = ! empty($resolutionNotes) ? "Catatan Solusi: _{$resolutionNotes}_\n\n" : '';

        return <<<TEXT
Halo Kak *{$customerName}*, sesi bantuan terkait tiket pengaduan telah diselesaikan oleh tim kami.

{$notesText}Jika di kemudian hari Kakak memerlukan informasi seputar SPKLU Jarvis, asisten virtual Jarvis siap melayani Kakak kembali 24 jam. Terima kasih dan salam sehat selalu! ⚡
TEXT;
    }

    /**
     * Get the human-readable Indonesian label for a ticket category.
     */
    public function getCategoryLabel(string $category): string
    {
        return match ($category) {
            'points_mismatch' => 'Ketidakcocokan Saldo Poin',
            'abnormal_transaction' => 'Transaksi Tidak Biasa / Gagal',
            'complaint' => 'Keluhan Pelayanan',
            default => 'Bantuan Customer Service',
        };
    }

    /**
     * Helper to send an outgoing WhatsApp message and persist to database.
     */
    protected function sendAndPersistMessage(Conversation $conversation, string $text): Message
    {
        $trimmedText = trim($text);

        // Kirim via WhatsApp Gateway
        $sent = $this->whatsappGateway->sendText(
            $conversation->phone,
            $trimmedText,
            $conversation->device_id
        );

        // Hentikan status typing jika ada
        $this->whatsappGateway->sendChatPresence(
            $conversation->phone,
            'stop',
            $conversation->device_id
        );

        // Simpan ke tabel messages
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'whatsapp_message_id' => null,
            'sender_type' => 'human',
            'is_from_me' => true,
            'text' => $trimmedText,
            'status' => $sent ? 'delivered' : 'failed',
            'sent_at' => now(),
        ]);

        // Update percakapan
        $conversation->update([
            'last_message' => $trimmedText,
            'last_message_time' => now(),
            'unread_count' => 0,
        ]);

        // Siarkan pembaruan pesan ke WebSocket Reverb
        broadcast(new MessageReceived($message, $conversation));

        return $message;
    }

    /**
     * Format a Ticket model into an array payload.
     */
    protected function formatTicket(Ticket $t): array
    {
        $customer = $t->customer ?: $t->conversation?->customer;
        $customerName = $customer?->name ?: ($t->conversation?->name ?: 'Pelanggan');
        $customerPhone = $customer?->phone ?: ($t->conversation?->phone ?: '-');

        return [
            'id' => $t->id,
            'ticket_code' => $t->ticket_code,
            'conversation_id' => $t->conversation_id,
            'customer_id' => $t->customer_id,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'vehicle' => $customer?->vehicle ?: ($t->conversation?->meta['vehicle'] ?? '-'),
            'driver_type' => $customer?->driver_type ?: ($t->conversation?->meta['driver_type'] ?? '-'),
            'category' => $t->category,
            'category_label' => $this->getCategoryLabel($t->category),
            'priority' => $t->priority,
            'status' => $t->status,
            'queue_position' => $t->status === 'queued' ? $this->calculateQueuePosition($t) : 0,
            'resolution_notes' => $t->resolution_notes,
            'metadata' => $t->metadata,
            'created_at' => $t->created_at?->toISOString(),
            'updated_at' => $t->updated_at?->toISOString(),
            'resolved_at' => $t->resolved_at?->toISOString(),
        ];
    }
}
