<?php

namespace App\Events\Bot;

use App\Models\Bot\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketQueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Ticket  $ticket  Tiket yang memicu pembaruan
     * @param  string  $action  'created' | 'serving' | 'resolved'
     * @param  array{total: int, queued: int, in_progress: int, resolved: int}|null  $summary  Ringkasan antrean
     */
    public function __construct(
        public Ticket $ticket,
        public string $action = 'created',
        public ?array $summary = null
    ) {
        $this->ticket->loadMissing(['conversation.customer', 'customer']);

        if ($this->summary === null) {
            $this->summary = [
                'total' => Ticket::count(),
                'queued' => Ticket::where('status', 'queued')->count(),
                'in_progress' => Ticket::where('status', 'in_progress')->count(),
                'resolved' => Ticket::where('status', 'resolved')->count(),
            ];
        }
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('tickets'),
        ];

        if ($this->ticket->conversation_id) {
            $channels[] = new Channel("conversation.{$this->ticket->conversation_id}");
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'TicketQueueUpdated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $customer = $this->ticket->customer ?: $this->ticket->conversation?->customer;
        $customerName = $customer?->name ?: ($this->ticket->conversation?->name ?: 'Pelanggan');
        $customerPhone = $customer?->phone ?: ($this->ticket->conversation?->phone ?: '-');

        // Kalkulasi posisi antrean dinamis jika tiket berstatus queued
        $queuePosition = 0;
        if ($this->ticket->status === 'queued') {
            $queuePosition = Ticket::where('status', 'queued')
                ->where('created_at', '<=', $this->ticket->created_at)
                ->count();
        }

        return [
            'action' => $this->action,
            'ticket' => [
                'id' => $this->ticket->id,
                'ticket_code' => $this->ticket->ticket_code,
                'conversation_id' => $this->ticket->conversation_id,
                'customer_id' => $this->ticket->customer_id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'vehicle' => $customer?->vehicle ?: ($this->ticket->conversation?->meta['vehicle'] ?? '-'),
                'driver_type' => $customer?->driver_type ?: ($this->ticket->conversation?->meta['driver_type'] ?? '-'),
                'category' => $this->ticket->category,
                'category_label' => match ($this->ticket->category) {
                    'points_mismatch' => 'Ketidakcocokan Saldo Poin',
                    'abnormal_transaction' => 'Transaksi Tidak Biasa / Gagal',
                    'complaint' => 'Keluhan Pelayanan',
                    default => 'Bantuan Customer Service',
                },
                'priority' => $this->ticket->priority,
                'status' => $this->ticket->status,
                'queue_position' => $queuePosition,
                'resolution_notes' => $this->ticket->resolution_notes,
                'metadata' => $this->ticket->metadata,
                'created_at' => $this->ticket->created_at?->toISOString(),
                'updated_at' => $this->ticket->updated_at?->toISOString(),
                'resolved_at' => $this->ticket->resolved_at?->toISOString(),
            ],
            'summary' => $this->summary,
        ];
    }
}
