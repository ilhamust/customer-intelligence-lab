<?php

namespace App\Models\Bot;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'conversations';

    protected $fillable = [
        'device_id',
        'chat_jid',
        'name',
        'phone',
        'last_message',
        'last_message_time',
        'unread_count',
        'status',
        'handler',
        'meta',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_time' => 'datetime',
            'unread_count' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * Messages belonging to this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id', 'id')->orderBy('sent_at', 'asc');
    }

    /**
     * WhatsApp channel associated with this conversation's device.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatsappChannel::class, 'device_id', 'device_id');
    }
}
