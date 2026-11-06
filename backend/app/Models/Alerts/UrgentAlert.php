<?php

namespace App\Models\Alerts;

use App\Models\Bot\Conversation;
use App\Models\Bot\Customer;
use App\Models\Bot\Ticket;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrgentAlert extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'urgent_alerts';

    protected $fillable = [
        'alert_code',
        'conversation_id',
        'customer_id',
        'ticket_id',
        'incident_category',
        'raw_message',
        'dispatched_recipients',
        'status',
        'acknowledged_by',
        'acknowledged_at',
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
            'dispatched_recipients' => 'array',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Conversation linked to this urgent alert.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'id');
    }

    /**
     * Customer linked to this urgent alert.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    /**
     * Ticket linked to this urgent alert.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
    }

    /**
     * Generate unique alert code in format ALT-XXXX (e.g. ALT-4921).
     */
    public static function generateAlertCode(): string
    {
        do {
            $code = 'ALT-'.mt_rand(1000, 9999);
        } while (static::where('alert_code', $code)->exists());

        return $code;
    }
}

