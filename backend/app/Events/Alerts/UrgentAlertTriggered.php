<?php

namespace App\Events\Alerts;

use App\Models\Alerts\UrgentAlert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UrgentAlertTriggered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public UrgentAlert $alert,
        public string $action = 'triggered'
    ) {
        $this->alert->loadMissing(['customer', 'conversation', 'ticket']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('alerts'),
            new Channel('tickets'),
        ];

        if ($this->alert->conversation_id) {
            $channels[] = new Channel("conversation.{$this->alert->conversation_id}");
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'UrgentAlertTriggered';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $customer = $this->alert->customer ?: $this->alert->conversation?->customer;
        $customerName = $customer?->name ?: ($this->alert->conversation?->name ?: 'Pelanggan');
        $customerPhone = $customer?->phone ?: ($this->alert->conversation?->phone ?: '-');

        return [
            'action' => $this->action,
            'alert' => [
                'id' => $this->alert->id,
                'alert_code' => $this->alert->alert_code,
                'conversation_id' => $this->alert->conversation_id,
                'customer_id' => $this->alert->customer_id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'ticket_id' => $this->alert->ticket_id,
                'ticket_code' => $this->alert->ticket?->ticket_code,
                'incident_category' => $this->alert->incident_category,
                'raw_message' => $this->alert->raw_message,
                'dispatched_recipients' => $this->alert->dispatched_recipients ?? [],
                'status' => $this->alert->status,
                'acknowledged_by' => $this->alert->acknowledged_by,
                'acknowledged_at' => $this->alert->acknowledged_at?->toIso8601String(),
                'resolved_at' => $this->alert->resolved_at?->toIso8601String(),
                'created_at' => $this->alert->created_at?->toIso8601String(),
            ],
        ];
    }
}

