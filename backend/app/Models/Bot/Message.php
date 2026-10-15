<?php

namespace App\Models\Bot;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'messages';

    protected $fillable = [
        'conversation_id',
        'whatsapp_message_id',
        'sender_type',
        'is_from_me',
        'text',
        'status',
        'sent_at',
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
            'is_from_me' => 'boolean',
            'sent_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * Conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'id');
    }
}
