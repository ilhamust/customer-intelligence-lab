<?php

namespace App\Models\Bot;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'tickets';

    protected $fillable = [
        'ticket_code',
        'conversation_id',
        'customer_id',
        'category',
        'priority',
        'status',
        'queue_position',
        'resolution_notes',
        'metadata',
        'resolved_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'queue_position' => 'integer',
            'metadata' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Conversation linked to this ticket.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'id');
    }

    /**
     * Customer who submitted/owns this ticket.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    /**
     * Generate unique ticket code in format TCK-XXXX (e.g. TCK-8472).
     */
    public static function generateTicketCode(): string
    {
        do {
            $code = 'TCK-'.mt_rand(1000, 9999);
        } while (static::where('ticket_code', $code)->exists());

        return $code;
    }
}
