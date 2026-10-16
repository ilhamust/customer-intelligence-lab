<?php

namespace App\Events\Bot;

use App\Models\Bot\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Conversation $conversation
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("conversation.{$this->conversation->id}"),
            new Channel('conversations'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ConversationUpdated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->conversation->id,
            'handler' => $this->conversation->handler,
            'status' => $this->conversation->status,
            'updated_at' => $this->conversation->updated_at?->toISOString(),
            'conversation' => [
                'id' => $this->conversation->id,
                'device_id' => $this->conversation->device_id,
                'name' => $this->conversation->name,
                'phone' => $this->conversation->phone,
                'last_message' => $this->conversation->last_message,
                'last_message_time' => $this->conversation->last_message_time?->toISOString(),
                'unread_count' => (int) $this->conversation->unread_count,
                'status' => $this->conversation->status,
                'handler' => $this->conversation->handler,
                'updated_at' => $this->conversation->updated_at?->toISOString(),
            ],
        ];
    }
}
