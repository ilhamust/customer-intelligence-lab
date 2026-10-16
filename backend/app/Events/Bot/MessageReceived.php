<?php

namespace App\Events\Bot;

use App\Models\Bot\Conversation;
use App\Models\Bot\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public ?Conversation $conversation;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message, ?Conversation $conversation = null)
    {
        $this->message = $message;

        if ($conversation !== null) {
            $this->conversation = $conversation;
        } elseif ($message->relationLoaded('conversation')) {
            $this->conversation = $message->getRelation('conversation');
        } elseif ($message->conversation_id && $message->getConnectionResolver()) {
            $this->conversation = Conversation::find($message->conversation_id);
        } else {
            $this->conversation = null;
        }
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("conversation.{$this->message->conversation_id}"),
            new Channel('conversations'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'MessageReceived';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'whatsapp_message_id' => $this->message->whatsapp_message_id,
                'sender' => $this->message->sender_type,
                'sender_type' => $this->message->sender_type,
                'is_from_me' => (bool) $this->message->is_from_me,
                'text' => $this->message->text,
                'status' => $this->message->status,
                'sent_at' => $this->message->sent_at?->toISOString() ?? $this->message->created_at?->toISOString(),
                'time' => $this->message->sent_at?->toISOString() ?? $this->message->created_at?->toISOString(),
                'meta' => $this->message->meta,
            ],
            'conversation' => $this->conversation ? [
                'id' => $this->conversation->id,
                'name' => $this->conversation->name,
                'phone' => $this->conversation->phone,
                'last_message' => $this->conversation->last_message,
                'last_message_time' => $this->conversation->last_message_time?->toISOString(),
                'unread_count' => (int) $this->conversation->unread_count,
                'status' => $this->conversation->status,
                'handler' => $this->conversation->handler,
            ] : null,
        ];
    }
}
